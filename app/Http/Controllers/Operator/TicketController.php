<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\BotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'open');
        $tickets = Ticket::with('participant')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('operator.tickets.index', compact('tickets', 'status'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['participant', 'messages' => fn ($q) => $q->orderBy('created_at')]);
        // Full history of participant for context
        $history = $ticket->participant->messages()
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return view('operator.tickets.show', compact('ticket', 'history'));
    }

    public function reply(Request $request, Ticket $ticket, BotService $bot)
    {
        $request->validate(['message' => 'required|string|max:4000']);

        if ($ticket->status === 'closed') {
            return back()->with('error', 'Обращение уже закрыто.');
        }

        $bot->sendOperatorReply($ticket, $request->input('message'), Auth::id());

        return back()->with('success', 'Ответ отправлен участнику.');
    }

    public function close(Ticket $ticket)
    {
        $ticket->close();
        return redirect()->route('operator.tickets.index')->with('success', 'Обращение #' . $ticket->id . ' закрыто.');
    }
}
