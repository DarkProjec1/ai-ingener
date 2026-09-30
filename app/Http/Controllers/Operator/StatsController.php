<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\LlmLog;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index()
    {
        $botResolved = LlmLog::where('escalated', false)->count();
        $escalated = LlmLog::where('escalated', true)->count();
        $openTickets = Ticket::where('status', 'open')->count();
        $closedTickets = Ticket::where('status', 'closed')->count();

        $avgResponse = Ticket::whereNotNull('first_response_at')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))) as avg_sec'))
            ->value('avg_sec');

        $avgResponseHuman = $avgResponse
            ? gmdate('H:i:s', (int) $avgResponse)
            : '—';

        return view('operator.stats', compact(
            'botResolved',
            'escalated',
            'openTickets',
            'closedTickets',
            'avgResponseHuman'
        ));
    }
}
