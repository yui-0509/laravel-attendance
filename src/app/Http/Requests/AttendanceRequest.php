<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
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
            'new_clock_in'  => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],

            // 休憩（配列）
            'breaks' => ['nullable', 'array'],
            'breaks.*.break_id'        => ['nullable', 'integer'],
            'breaks.*.new_break_start' => ['nullable', 'date_format:H:i'],
            'breaks.*.new_break_end'   => ['nullable', 'date_format:H:i'],

            // 備考
            'remark' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required'   => '出勤時刻は必須です。',
            'new_clock_in.date_format'=> '出勤時刻は HH:MM 形式で入力してください（例: 09:00）。',
            'new_clock_out.required'  => '退勤時刻は必須です。',
            'new_clock_out.date_format'=> '退勤時刻は HH:MM 形式で入力してください（例: 18:00）。',

            'breaks.array'            => '休憩データの形式が不正です。',
            'breaks.*.new_break_start.date_format' => '休憩入は HH:MM 形式で入力してください。',
            'breaks.*.new_break_end.date_format'   => '休憩戻は HH:MM 形式で入力してください。',

            'remark.required'         => '備考を記入してください',
            'remark.max'              => '備考は255文字以内で入力してください。',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $in  = $this->input('new_clock_in');
            $out = $this->input('new_clock_out');

            // 出退勤の相関チェック
            if ($in && $out) {
                if (!$this->isTimeA_before_B($in, $out)) {
                    $v->errors()->add('new_clock_in', '出勤時間もしくは退勤時間が不適切な値です');
                }
            }

            // 休憩の相関チェック
            $breaks = $this->input('breaks', []);
            if (!is_array($breaks)) $breaks = [];

            foreach ($breaks as $idx => $row) {
                $start = $row['new_break_start'] ?? null;
                $end   = $row['new_break_end']   ?? null;

                // 末尾の空行はスキップ（両方未入力ならOK）
                if (!$start && !$end) {
                    continue;
                }

                // 片方だけ入力 → エラー
                if (($start && !$end) || (!$start && $end)) {
                    $v->errors()->add("breaks.$idx.new_break_start", '休憩は「入」と「戻」を両方入力してください。');
                    $v->errors()->add("breaks.$idx.new_break_end",   '休憩は「入」と「戻」を両方入力してください。');
                    continue;
                }

                // フォーマットは rules() 側でチェック済み。ここでは相関のみ。
                if ($in && !$this->isTimeA_before_B($in, $start)) {
                    $v->errors()->add("breaks.$idx.new_break_start", '休憩時間が不適切な値です');
                }

                if ($out && !$this->isTimeA_before_B($start, $out)) {
                    // start < out（休憩は退勤前に開始している必要）
                    $v->errors()->add("breaks.$idx.new_break_start", '休憩時間が不適切な値です');
                }

                if (!$this->isTimeA_before_B($start, $end)) {
                    $v->errors()->add("breaks.$idx.new_break_end", '休憩時間が不適切な値です');
                }

                // ★通常仕様：休憩戻は退勤「以前/同時刻」まで
                if ($out && !$this->isTimeA_before_B($end, $out) && $end !== $out) {
                    // end < out または end == out を許容。end > out はNG。
                    $v->errors()->add("breaks.$idx.new_break_end", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }

    /** 時刻 "HH:MM" を分に変換 */
    private function toMinutes(string $hhmm): ?int
    {
        if (!preg_match('/^(?<h>[01]\d|2[0-3]):(?<m>[0-5]\d)$/', $hhmm, $m)) {
            return null;
        }
        return ((int)$m['h']) * 60 + ((int)$m['m']);
    }

    /** A < B を判定（同一日想定） */
    private function isTimeA_before_B(string $a, string $b): bool
    {
        $ma = $this->toMinutes($a);
        $mb = $this->toMinutes($b);
        if ($ma === null || $mb === null) return false; // フォーマット外は false
        return $ma < $mb;
    }
}
