<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Usage_Estimator {
    private const OPTION = 'idg_usage_records';
    private const MAX_RECORDS = 500;

    private static function rates_for_model(string $model): array {
        $model = strtolower($model);
        $rates = [
            'gpt-5.5' => ['input' => 5.00, 'output' => 30.00],
            'gpt-5.4-mini' => ['input' => 0.75, 'output' => 4.50],
            'gpt-5.4' => ['input' => 2.50, 'output' => 15.00],
            'gpt-5.1' => ['input' => 1.25, 'output' => 10.00],
        ];
        foreach ($rates as $needle => $rate) {
            if (strpos($model, $needle) !== false) {
                return $rate;
            }
        }
        return ['input' => 0.75, 'output' => 4.50];
    }

    public static function estimate_cost(string $model, int $input_tokens, int $output_tokens): float {
        $rate = self::rates_for_model($model);
        return (($input_tokens / 1000000) * $rate['input']) + (($output_tokens / 1000000) * $rate['output']);
    }

    public static function normalize_usage(array $usage): array {
        $input = (int) ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));
        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
        ];
    }

    public static function record(string $action, array $usage, string $workflow_id = '', string $model = ''): array {
        $normalized = self::normalize_usage($usage);
        $model = $model !== '' ? $model : (string) ((get_option(IDG_OPTION_KEY, [])['model'] ?? 'gpt-5.4-mini'));
        $cost = self::estimate_cost($model, $normalized['input_tokens'], $normalized['output_tokens']);
        $record = [
            'time' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'workflow_id' => sanitize_text_field($workflow_id),
            'action' => sanitize_key($action),
            'model' => sanitize_text_field($model),
            'input_tokens' => $normalized['input_tokens'],
            'output_tokens' => $normalized['output_tokens'],
            'total_tokens' => $normalized['total_tokens'],
            'estimated_cost' => round($cost, 6),
        ];

        $records = get_option(self::OPTION, []);
        if (!is_array($records)) {
            $records = [];
        }
        $records[] = $record;
        if (count($records) > self::MAX_RECORDS) {
            $records = array_slice($records, -self::MAX_RECORDS);
        }
        update_option(self::OPTION, $records, false);
        return $record;
    }

    public static function month_summary(): array {
        $records = get_option(self::OPTION, []);
        if (!is_array($records)) {
            $records = [];
        }
        $month = current_time('Y-m');
        $cost = 0.0;
        $tokens = 0;
        $executions = 0;
        $by_action = [];
        foreach ($records as $record) {
            $time = (string) ($record['time'] ?? '');
            if (strpos($time, $month) !== 0) {
                continue;
            }
            $executions++;
            $tokens += (int) ($record['total_tokens'] ?? 0);
            $cost += (float) ($record['estimated_cost'] ?? 0);
            $action = (string) ($record['action'] ?? 'unknown');
            if (!isset($by_action[$action])) {
                $by_action[$action] = 0;
            }
            $by_action[$action]++;
        }
        return [
            'month' => $month,
            'executions' => $executions,
            'tokens' => $tokens,
            'estimated_cost' => round($cost, 4),
            'by_action' => $by_action,
        ];
    }

    public static function reference_balance(): array {
        $settings = get_option(IDG_OPTION_KEY, []);
        $initial = isset($settings['openai_reference_balance']) ? (float) $settings['openai_reference_balance'] : 0.0;
        $summary = self::month_summary();
        $balance = $initial > 0 ? max(0, $initial - (float) $summary['estimated_cost']) : 0.0;
        return [
            'initial' => $initial,
            'estimated_spend' => (float) $summary['estimated_cost'],
            'estimated_balance' => round($balance, 4),
            'summary' => $summary,
        ];
    }
}
