<div class="grid grid-cols-2 gap-4 mb-4">
    <div>
        <label class="text-sm text-gray-600">始業時間</label>
        <input type="time" name="work_start_time" value="{{ $rule?->work_start_time ?? '09:00' }}" class="w-full border rounded px-3 py-2 text-sm" required>
    </div>
    <div>
        <label class="text-sm text-gray-600">終業時間</label>
        <input type="time" name="work_end_time" value="{{ $rule?->work_end_time ?? '18:00' }}" class="w-full border rounded px-3 py-2 text-sm" required>
    </div>
    <div>
        <label class="text-sm text-gray-600">デフォルト休憩（分）</label>
        <input type="number" name="default_break_minutes" value="{{ $rule?->default_break_minutes ?? 60 }}" min="0" class="w-full border rounded px-3 py-2 text-sm" required>
    </div>
    <div>
        <label class="text-sm text-gray-600">丸め単位（分）</label>
        <select name="rounding_unit" class="w-full border rounded px-3 py-2 text-sm">
            @foreach([1, 5, 10, 15, 30, 60] as $unit)
                <option value="{{ $unit }}" {{ ($rule?->rounding_unit ?? 1) == $unit ? 'selected' : '' }}>{{ $unit }}分</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm text-gray-600">出勤丸め</label>
        <select name="clock_in_rounding" class="w-full border rounded px-3 py-2 text-sm">
            <option value="none" {{ ($rule?->clock_in_rounding ?? 'none') === 'none' ? 'selected' : '' }}>なし</option>
            <option value="ceil" {{ ($rule?->clock_in_rounding ?? '') === 'ceil' ? 'selected' : '' }}>切り上げ</option>
            <option value="floor" {{ ($rule?->clock_in_rounding ?? '') === 'floor' ? 'selected' : '' }}>切り捨て</option>
        </select>
    </div>
    <div>
        <label class="text-sm text-gray-600">退勤丸め</label>
        <select name="clock_out_rounding" class="w-full border rounded px-3 py-2 text-sm">
            <option value="none" {{ ($rule?->clock_out_rounding ?? 'none') === 'none' ? 'selected' : '' }}>なし</option>
            <option value="ceil" {{ ($rule?->clock_out_rounding ?? '') === 'ceil' ? 'selected' : '' }}>切り上げ</option>
            <option value="floor" {{ ($rule?->clock_out_rounding ?? '') === 'floor' ? 'selected' : '' }}>切り捨て</option>
        </select>
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-4">
    <div>
        <label class="text-sm text-gray-600">早出カット時刻</label>
        <input type="time" name="early_clock_in_cutoff" value="{{ $rule?->early_clock_in_cutoff ?? '' }}" class="w-full border rounded px-3 py-2 text-sm">
        <p class="text-xs text-gray-400 mt-1">この時刻より前の出勤は、この時刻に丸められます</p>
    </div>
    <div>
        <label class="text-sm text-gray-600">早出カット時刻（午後）</label>
        <input type="time" name="early_clock_in_cutoff_pm" value="{{ $rule?->early_clock_in_cutoff_pm ?? '' }}" class="w-full border rounded px-3 py-2 text-sm">
        <p class="text-xs text-gray-400 mt-1">午後セッション用（配達の2回出勤等）</p>
    </div>
</div>

<div class="mb-4">
    <label class="flex items-center space-x-2">
        <input type="hidden" name="allow_multiple_clock_ins" value="0">
        <input type="checkbox" name="allow_multiple_clock_ins" value="1" {{ ($rule?->allow_multiple_clock_ins ?? false) ? 'checked' : '' }} class="rounded">
        <span class="text-sm text-gray-600">複数出勤を許可</span>
    </label>
</div>

<div class="mb-4">
    <label class="text-sm text-gray-600">段階的休憩ルール（JSON）</label>
    <textarea name="break_tiers" rows="2" class="w-full border rounded px-3 py-2 text-sm font-mono" placeholder='[{"thresholdHours": 6, "breakMinutes": 45}]'>{{ $rule?->break_tiers ? json_encode($rule->break_tiers, JSON_UNESCAPED_UNICODE) : '' }}</textarea>
</div>
