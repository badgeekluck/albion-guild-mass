@if (session('success'))
    <div class="flash flash-success" role="status" style="max-width: 1600px; margin: 0 auto 15px auto; padding: 10px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #6ee7b7;">
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="flash flash-error" role="alert" style="max-width: 1600px; margin: 0 auto 15px auto; padding: 10px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5;">
        {{ session('error') }}
    </div>
@endif
@if ($errors->any() && ! ($hideValidationErrors ?? false))
    <div class="flash flash-error" role="alert" style="max-width: 1600px; margin: 0 auto 15px auto; padding: 10px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5;">
        <ul style="margin: 0; padding-left: 18px; list-style: disc;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
