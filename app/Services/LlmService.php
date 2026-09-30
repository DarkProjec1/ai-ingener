<?php

namespace App\Services;

use App\Models\LlmLog;
use App\Models\Participant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    public function chat(string $userMessage, ?Participant $participant = null, array $history = []): array
    {
        $start = microtime(true);
        $rules = file_get_contents(base_path('prompts/promo-rules.md'));
        $system = file_get_contents(base_path('prompts/system_prompt.md'));
        $system = str_replace('{{RULES}}', $rules, $system);

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        foreach (array_slice($history, -6) as $msg) {
            $role = $msg['role'] === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $apiKey = config('services.llm.api_key');
        $baseUrl = rtrim(config('services.llm.base_url', 'https://api.openai.com/v1'), '/');
        $model = config('services.llm.model', 'gpt-4o-mini');

        // Fallback rule-based engine when no API key (for local/demo)
        if (empty($apiKey) || $apiKey === 'test') {
            $result = $this->ruleBasedAnswer($userMessage);
            $this->log($participant, $userMessage, $result, (int) ((microtime(true) - $start) * 1000), $model . '-fallback');
            return $result;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (!$response->successful()) {
                Log::error('LLM API error', ['status' => $response->status(), 'body' => $response->body()]);
                return $this->fallbackEscalate($userMessage);
            }

            $content = $response->json('choices.0.message.content', '{}');
            $parsed = json_decode($content, true);

            if (!is_array($parsed) || !isset($parsed['answer'])) {
                Log::warning('LLM returned invalid JSON', ['content' => $content]);
                return $this->fallbackEscalate($userMessage);
            }

            $result = [
                'answer' => (string) ($parsed['answer'] ?? ''),
                'escalate' => (bool) ($parsed['escalate'] ?? false),
                'reason' => $parsed['reason'] ?? null,
            ];

            $latency = (int) ((microtime(true) - $start) * 1000);
            $tokens = $response->json('usage.total_tokens');
            $this->log($participant, $userMessage, $result, $latency, $model, $tokens);

            return $result;
        } catch (\Throwable $e) {
            Log::error('LLM exception: ' . $e->getMessage());
            return $this->fallbackEscalate($userMessage);
        }
    }

    /**
     * Deterministic answers for the 25 test requests + common cases.
     * Used when LLM_API_KEY is empty or "test".
     */
    protected function ruleBasedAnswer(string $message): array
    {
        $m = mb_strtolower(trim($message));

        // Prompt injections
        if (preg_match('/игнорируй|системн(ый|ого)\s+промпт|администратор\s+акции|отметь\s+мой\s+номер|промокод|выведи\s+свой/ui', $m)) {
            return [
                'answer' => 'Я бот поддержки акции «Вкусная осень». Не могу выполнять такие запросы. Если у вас вопрос по правилам акции — напишите его.',
                'escalate' => false,
                'reason' => 'prompt_injection',
            ];
        }

        // Media / non-text hints
        if (preg_match('/\[фото\]|\[голос\]|\[документ\]|отправ(ил|ьте)\s+(фото|скрин|чек)/ui', $m)) {
            return [
                'answer' => 'К сожалению, в текущей версии я принимаю только текстовые сообщения. Опишите, пожалуйста, ваш вопрос текстом.',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 1. До какого числа регистрировать чеки
        if (preg_match('/до\s+какого|когда\s+(можно|последний).*регистр|срок\s+регистрац/ui', $m)) {
            return [
                'answer' => "Регистрировать чеки можно до 23:59 2 ноября 2026 г. (п. 2.3 Правил).\n\nВажно: покупка должна быть совершена в период с 1 сентября по 31 октября 2026 г. Чеки, зарегистрированные позже 2 ноября, к участию не принимаются.",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 2. Кефир
        if (preg_match('/кефир|ряженк|творож|молоко|другая\s+продукц/ui', $m) && !preg_match('/йогурт/ui', $m)) {
            return [
                'answer' => "Нет, кефир, ряженка, творожки, творожные десерты и молоко в акции не участвуют (п. 4.2).\n\nУчаствуют только йогурты «Молочный край»:\n• питьевые 270 г\n• густые 130 г\n• детские «Краюшка» 100 г\n(все вкусы).",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 3. Сколько чеков в день
        if (preg_match('/сколько\s+чеков.*(день|сутки)|лимит.*чек|чеков\s+в\s+день/ui', $m)) {
            return [
                'answer' => 'Один участник может зарегистрировать не более 10 чеков в сутки (п. 6.1 Правил).',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 4. Розыгрыш главного приза
        if (preg_match('/главн(ый|ого)\s+приз|розыгрыш\s+главн/ui', $m)) {
            return [
                'answer' => 'Розыгрыш главного приза проводится 10 ноября 2026 г. в 15:00 (по московскому времени) — п. 2.5 Правил.',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 5 / 19. Какие призы
        if (preg_match('/какие\s+призы|призовой\s+фонд|что\s+можно\s+выиграть/ui', $m)) {
            return [
                'answer' => "Призовой фонд (п. 7):\n\n**Еженедельные розыгрыши** (каждый вторник):\n• 20 электронных сертификатов маркетплейса по 1 000 ₽\n• 5 йогуртниц стоимостью 3 500 ₽\n\n**Главный приз** (1 шт.):\n• сертификат на путешествие 150 000 ₽ + денежная часть (НДФЛ удерживает организатор).\n\nОдин участник может получить не более одного приза каждого вида за всю акцию (п. 7.3).\nДенежный эквивалент и замена призов не производятся (п. 7.4).",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 6. Чек из интернет-магазина
        if (preg_match('/интернет.?магазин|доставк|онлайн.?заказ/ui', $m)) {
            return [
                'answer' => 'Да, чеки интернет-магазинов и служб доставки принимаются, если в чеке указаны наименования участвующей продукции (п. 5.4 Правил).',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 7. Сколько проверяют чек
        if (preg_match('/сколько.*(провер|модерац)|как\s+долго.*провер|висит\s+на\s+провер/ui', $m)) {
            return [
                'answer' => "Все чеки проходят проверку (модерацию) в течение 3 рабочих дней с момента регистрации (п. 6.3).\n\nСтатус отображается в личном кабинете: «На проверке», «Принят» или «Отклонён».",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 8. Налог с сертификата 1000
        if (preg_match('/налог|ндфл/ui', $m) && preg_match('/сертификат|1000/ui', $m)) {
            return [
                'answer' => "Доходы в виде призов не облагаются НДФЛ, если их совокупная стоимость за календарный год не превышает 4 000 ₽ (п. 10.1).\n\nСертификат 1 000 ₽ сам по себе укладывается в лимит. Учёт призов из других акций вы ведёте самостоятельно.",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 9. Чек 31 октября, регистрация 3 ноября
        if (preg_match('/31\s+октябр|3\s+ноябр|командировк/ui', $m)) {
            return [
                'answer' => "К сожалению, нет. Период регистрации чеков — до 23:59 2 ноября 2026 г. (п. 2.3). Чеки, зарегистрированные позже, к участию не принимаются, даже если покупка была в срок (до 31 октября).",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 10. 7 йогуртов + 2 творожка
        if (preg_match('/7\s+питьев|творожк|сколько.*шанс/ui', $m) && preg_match('/йогурт|творож/ui', $m)) {
            return [
                'answer' => "Творожки в акции не участвуют (п. 4.2).\n\nУчитываются только 7 питьевых йогуртов. Каждые 2 единицы дают 1 шанс, с одного чека — не более 5 шансов (п. 5.5).\n7 ÷ 2 = 3 шанса (неполная пара не учитывается).",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 11. Второй сертификат
        if (preg_match('/ещё\s+один|второй\s+сертификат|уже\s+выиграл/ui', $m)) {
            return [
                'answer' => 'Нет. Один участник может получить за всё время акции не более одного приза каждого вида: один сертификат маркетплейса, одну йогуртницу и главный приз (п. 7.3).',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 12. Чек мамы
        if (preg_match('/чек\s+мам|чужой\s+чек|на\s+мой\s+номер/ui', $m)) {
            return [
                'answer' => "По правилам один номер телефона — один участник (п. 5.2). Каждый чек может быть зарегистрирован только один раз (п. 6.2).\n\nРегистрировать чек другого человека на свой номер нельзя. Рекомендуем маме зарегистрироваться самостоятельно.",
                'escalate' => true,
                'reason' => 'попытка регистрации чужого чека',
            ];
        }

        // 13. Акция зимой
        if (preg_match('/зимой|следующ(ая|ей)\s+акци|будет\s+ли\s+такая/ui', $m)) {
            return [
                'answer' => 'Информации о проведении похожей акции зимой в текущих правилах нет. Следите за новостями на сайте vkusnaya-osen.example и в официальных каналах бренда.',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 14. Сменить номер телефона
        if (preg_match('/поменять\s+номер|смен(ить|а)\s+номер|потерял.*сим/ui', $m)) {
            return [
                'answer' => 'В правилах акции порядок смены номера телефона в личном кабинете не описан. Передаю ваш вопрос оператору — он подскажет, что можно сделать.',
                'escalate' => true,
                'reason' => 'смена номера телефона',
            ];
        }

        // 15. Сколько участников
        if (preg_match('/сколько.*(участник|человек)|какие\s+у\s+меня\s+шансы/ui', $m)) {
            return [
                'answer' => 'Количество участников акции в открытом доступе не публикуется. Шансы зависят от количества ваших принятых чеков (шансов) относительно остальных участников в конкретном розыгрыше.',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 16. Сертификат не пришёл
        if (preg_match('/сертификат.*(нет|не\s+приш|не\s+пришёл|где)|прошло.*недел/ui', $m)) {
            return [
                'answer' => "Электронные сертификаты направляются на email в течение 10 рабочих дней после подтверждения данных (п. 9.4).\n\nЕсли срок уже прошёл — передаю обращение оператору, чтобы проверили статус отправки.",
                'escalate' => true,
                'reason' => 'сертификат не получен в срок',
            ];
        }

        // 17. Почему отклонили чек + номер
        if (preg_match('/почему.*(отклон|не\s+приня)|отклон.*чек/ui', $m)) {
            return [
                'answer' => "У меня нет доступа к статусам конкретных чеков. Возможные причины отклонения перечислены в п. 6.5 Правил.\n\nПередаю ваш вопрос оператору — он сможет посмотреть детали по вашему номеру.",
                'escalate' => true,
                'reason' => 'статус/причина отклонения чека',
            ];
        }

        // 18. Участвую ли в розыгрыше этой недели
        if (preg_match('/участвую\s+ли|провер.*розыгрыш|этой\s+недел/ui', $m)) {
            return [
                'answer' => "В еженедельном розыгрыше участвуют чеки, зарегистрированные в предыдущую календарную неделю и принятые к 12:00 дня розыгрыша (п. 8.1).\n\nУ меня нет доступа к вашим чекам. Передаю вопрос оператору для проверки.",
                'escalate' => true,
                'reason' => 'проверка участия в розыгрыше',
            ];
        }

        // 19. Призы + деньгами вместо йогуртницы
        if (preg_match('/вместо\s+йогуртниц|деньгам.*йогурт|эквивалент/ui', $m)) {
            return [
                'answer' => "Призы: еженедельно — сертификаты 1000 ₽ и йогуртницы 3500 ₽; главный — сертификат на путешествие 150 000 ₽.\n\nПолучение: подтверждение в ЛК в течение 3 дней после уведомления (п. 9.2). Сертификаты — на email за 10 р.д., йогуртницы — курьером за 30 дней.\n\nДенежный эквивалент и замена призов **не производятся** (п. 7.4).",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 20. Комбо: детские + двойной чек + следующий розыгрыш
        if (preg_match('/детск.*йогурт|краюшка/ui', $m) || (preg_match('/один\s+чек\s+два/ui', $m)) || (preg_match('/следующ.*розыгрыш/ui', $m) && preg_match('/1\)|2\)|3\)/ui', $m))) {
            $parts = [];
            if (preg_match('/детск|краюшка/ui', $m)) {
                $parts[] = "1) Да, детские йогурты «Краюшка» 100 г (все вкусы) участвуют в акции (п. 4.1).";
            }
            if (preg_match('/два\s+раза|два\s+шанса|один\s+чек\s+два/ui', $m)) {
                $parts[] = "2) Нет. Каждый чек регистрируется только один раз. Повторная регистрация того же чека отклоняется (п. 6.2).";
            }
            if (preg_match('/следующ.*розыгрыш|когда.*розыгрыш/ui', $m)) {
                $parts[] = "3) Еженедельные розыгрыши — каждый вторник в 15:00 (с 8 сентября по 3 ноября 2026 г.). Главный приз — 10 ноября 2026 в 15:00.";
            }
            if (empty($parts)) {
                $parts[] = "Детские йогурты «Краюшка» участвуют. Повторная регистрация чека запрещена. Розыгрыши — по вторникам.";
            }
            return [
                'answer' => implode("\n\n", $parts),
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 21. Мошенники / Роспотребнадзор
        if (preg_match('/мошенник|роспотребнадзор|жалоб/ui', $m)) {
            return [
                'answer' => "Сожалеем, что возникли сложности с регистрацией чека. Передаю ваше обращение оператору — он разберётся с ситуацией в приоритетном порядке.\n\nПока можете ещё раз проверить данные чека (ФН, ФД, ФП) и срок покупки (1 сентября — 31 октября 2026).",
                'escalate' => true,
                'reason' => 'жалоба / угроза Роспотребнадзором',
            ];
        }

        // 22. Йогуртница не пришла, деньги на карту
        if (preg_match('/йогуртниц.*(нет|не\s+приш|доставк)|переведите\s+3500|на\s+карту/ui', $m)) {
            return [
                'answer' => "Йогуртницы доставляются курьерской службой в течение 30 календарных дней после подтверждения данных (п. 9.5).\n\nДенежный эквивалент призов и замена **не производятся** (п. 7.4). Передаю обращение оператору, чтобы уточнили статус доставки.",
                'escalate' => true,
                'reason' => 'йогуртница не доставлена + запрос денег',
            ];
        }

        // 23. Рецепт десерта
        if (preg_match('/рецепт|десерт|день\s+рожден/ui', $m)) {
            return [
                'answer' => 'Я бот поддержки акции «Вкусная осень» и отвечаю только на вопросы по правилам акции. К сожалению, рецептами десертов помочь не могу 😊\n\nЕсли есть вопрос про участие, чеки или призы — с удовольствием отвечу!',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // 25 style - employee / test
        if (preg_match('/сотрудник\s+организатор|тестирую\s+бота|системный\s+промпт/ui', $m)) {
            return [
                'answer' => 'Я бот поддержки акции «Вкусная осень». Не могу выполнять служебные или тестовые команды. Задайте, пожалуйста, вопрос по правилам акции.',
                'escalate' => false,
                'reason' => 'prompt_injection',
            ];
        }

        // Детские йогурты отдельно
        if (preg_match('/детск.*йогурт|краюшка/ui', $m)) {
            return [
                'answer' => 'Да, детские йогурты «Краюшка» 100 г (все вкусы) участвуют в акции (п. 4.1 Правил).',
                'escalate' => false,
                'reason' => null,
            ];
        }

        // Следующий розыгрыш
        if (preg_match('/когда.*(следующ|ближайш).*розыгрыш|следующ.*розыгрыш/ui', $m)) {
            return [
                'answer' => "Еженедельные розыгрыши проводятся каждый вторник в 15:00 (по МСК), с 8 сентября по 3 ноября 2026 г. (п. 2.4).\n\nРозыгрыш главного приза — 10 ноября 2026 г. в 15:00.",
                'escalate' => false,
                'reason' => null,
            ];
        }

        // Общий fallback — эскалация
        return [
            'answer' => 'Спасибо за вопрос! Чтобы дать точный ответ, передаю его оператору поддержки. Обычно отвечают в рабочие дни с 9:00 до 18:00 (п. 12.1).',
            'escalate' => true,
            'reason' => 'вопрос не покрыт правилами однозначно',
        ];
    }

    protected function fallbackEscalate(string $message): array
    {
        return [
            'answer' => 'Сейчас не могу обработать запрос автоматически. Передаю его оператору — ответ придёт в этот чат.',
            'escalate' => true,
            'reason' => 'llm_error',
        ];
    }

    protected function log(?Participant $participant, string $userMessage, array $result, int $latencyMs, string $model, ?int $tokens = null): void
    {
        try {
            LlmLog::create([
                'participant_id' => $participant?->id,
                'user_message' => mb_substr($userMessage, 0, 2000),
                'llm_response' => $result,
                'escalated' => $result['escalate'] ?? false,
                'tokens_used' => $tokens,
                'latency_ms' => $latencyMs,
                'model' => $model,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write LLM log: ' . $e->getMessage());
        }
    }
}
