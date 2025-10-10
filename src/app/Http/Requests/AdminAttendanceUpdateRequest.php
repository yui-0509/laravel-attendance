<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'clock_in' => ['required', 'date_format:H:i'],
            'clock_out' => ['required', 'date_format:H:i'],

            'breaks' => ['nullable', 'array'],
            'breaks.*.break_id' => ['nullable', 'integer'],
            'breaks.*.break_start' => ['nullable', 'date_format:H:i'],
            'breaks.*.break_end' => ['nullable', 'date_format:H:i'],

            'remark' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM 形式で入力してください（例: 09:00）。',
            'clock_out.required' => '退勤時刻は必須です。',
            'clock_out.date_format' => '退勤時刻は HH:MM 形式で入力してください（例: 18:00）。',

            'breaks.array' => '休憩データの形式が不正です。',
            'breaks.*.break_start.date_format' => '休憩入は HH:MM 形式で入力してください。',
            'breaks.*.break_end.date_format' => '休憩戻は HH:MM 形式で入力してください。',

            'remark.required' => '備考を記入してください',
            'remark.max' => '備考は255文字以内で入力してください。',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function () use ($validator) {
            $in = $this->input('clock_in');
            $out = $this->input('clock_out');

            if ($in && $out) {
                if (! $this->isTimeA_before_B($in, $out)) {
                    $validator->errors()->add('clock_in', '出勤時間もしくは退勤時間が不適切な値です');
                }
            }

            $breaks = $this->input('breaks', []);
            if (! is_array($breaks)) {
                $breaks = [];
            }

            foreach ($breaks as $idx => $row) {
                $start = $row['break_start'] ?? null;
                $end = $row['break_end'] ?? null;

                if (! $start && ! $end) {
                    continue;
                }

                if (($start && ! $end) || (! $start && $end)) {
                    $validator->errors()->add("breaks.$idx.break_start", '休憩は「入」と「戻」を両方入力してください。');
                    $validator->errors()->add("breaks.$idx.break_end", '休憩は「入」と「戻」を両方入力してください。');

                    continue;
                }

                if ($in && ! $this->isTimeA_before_B($in, $start)) {
                    $validator->errors()->add("breaks.$idx.break_start", '休憩時間が不適切な値です');
                }

                if ($out && ! $this->isTimeA_before_B($start, $out)) {
                    $validator->errors()->add("breaks.$idx.break_start", '休憩時間が不適切な値です');
                }

                if (! $this->isTimeA_before_B($start, $end)) {
                    $validator->errors()->add("breaks.$idx.break_end", '休憩時間が不適切な値です');
                }

                if ($out && ! $this->isTimeA_before_B($end, $out) && $end !== $out) {
                    $validator->errors()->add("breaks.$idx.break_end", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }

    private function toMinutes(string $hhmm): ?int
    {
        if (! preg_match('/^(?<h>[01]\d|2[0-3]):(?<m>[0-5]\d)$/', $hhmm, $m)) {
            return null;
        }

        return ((int) $m['h']) * 60 + ((int) $m['m']);
    }

    private function isTimeA_before_B(string $a, string $b): bool
    {
        $ma = $this->toMinutes($a);
        $mb = $this->toMinutes($b);
        if ($ma === null || $mb === null) {
            return false;
        }

        return $ma < $mb;
    }
}
