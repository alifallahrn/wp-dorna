(function ($) {
    var cfg = window.wpDornaFullSync;
    if (!cfg) {
        return;
    }

    var state = cfg.state;
    var timer = null;
    var busy = false;

    var labels = {
        idle: 'آماده‌ی شروع',
        running: 'در حال اجرا',
        paused: 'متوقف‌شده',
        completed: 'تکمیل شد',
        cancelled: 'لغو شد',
        failed: 'به‌دلیل خطاهای پیاپی متوقف شد'
    };

    var visible = {
        start: ['idle', 'completed', 'cancelled'],
        pause: ['running'],
        resume: ['paused', 'failed'],
        cancel: ['running', 'paused', 'failed']
    };

    function fa(n) {
        return Number(n || 0).toLocaleString('fa-IR');
    }

    function eta(s) {
        if (s.status !== 'running' || !s.pages_left) {
            return '';
        }
        var minutes = Math.max(1, Math.ceil(s.pages_left * (s.delay + 1) / 60));
        return 'زمان باقی‌مانده: حدود ' + fa(minutes) + ' دقیقه';
    }

    function note(s) {
        var parts = [];

        if (s.status === 'running' && s.consecutive_errors > 0) {
            parts.push('درنا پاسخ نداد؛ بعد از چند لحظه دوباره تلاش می‌شود (' + fa(s.consecutive_errors) + ' از 5).');
        }
        if (s.status === 'failed' && s.last_error) {
            parts.push('آخرین خطا: ' + s.last_error + ' — بعد از رفع مشکل «ادامه» را بزنید.');
        }
        if (s.status === 'completed') {
            parts.push('از این به بعد تغییرات هر دقیقه به‌صورت خودکار دریافت می‌شود.');
        }
        if (s.stock_held > 0) {
            parts.push('موجودی ' + fa(s.stock_held) + ' کالا به‌دلیل سفارش‌های هنوز ارسال‌نشده به درنا تغییر نکرد.');
        }
        if (s.inactive > 0) {
            parts.push(fa(s.inactive) + ' کالا در درنا غیرفعال است و تغییری نکرد.');
        }

        return parts.join(' ');
    }

    function render(s) {
        $('#wp-dorna-fs-status').text(labels[s.status] || s.status);
        $('#wp-dorna-fs-eta').text(eta(s));
        $('#wp-dorna-fs-fill').css('width', s.percent + '%');
        $('#wp-dorna-fs-percent').text(fa(s.percent) + '٪');
        $('#wp-dorna-fs .wp-dorna-fs-bar').attr('aria-valuenow', s.percent);

        $('#wp-dorna-fs-checked').text(fa(s.checked));
        $('#wp-dorna-fs-total').text(fa(s.total));
        $('#wp-dorna-fs-updated').text(fa(s.updated));
        $('#wp-dorna-fs-unchanged').text(fa(s.unchanged));
        $('#wp-dorna-fs-missing').text(fa(s.missing));
        $('#wp-dorna-fs-failed').text(fa(s.failed));
        $('#wp-dorna-fs-note').text(note(s));

        $('#wp-dorna-fs [data-fs]').each(function () {
            var $btn = $(this);
            $btn.toggle(visible[$btn.data('fs')].indexOf(s.status) !== -1);
        });

        var $errors = $('#wp-dorna-fs-errors').empty();
        $.each(s.errors || [], function (i, line) {
            $('<li>').text(line).appendTo($errors);
        });

        var skus = s.not_in_dorna_skus || [];
        $('#wp-dorna-fs-report').toggle(s.status === 'completed' && s.not_in_dorna > 0);
        $('#wp-dorna-fs-report-count').text(fa(s.not_in_dorna));
        $('#wp-dorna-fs-report-list').val(skus.join('\n'));

        schedule();
    }

    function schedule() {
        if (state.status === 'running' && !timer) {
            // The server paces the calls itself; an early tick is simply a no-op.
            timer = setInterval(function () {
                request('tick');
            }, Math.max(2, state.delay) * 1000);
        } else if (state.status !== 'running' && timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function request(command) {
        if (busy) {
            return;
        }
        busy = true;

        $.post(cfg.ajaxUrl, {
            action: 'wp_dorna_full_sync',
            command: command,
            _ajax_nonce: cfg.nonce
        }).done(function (response) {
            if (response.success) {
                state = response.data;
                render(state);
            } else {
                window.alert(response.data.message);
            }
        }).fail(function () {
            $('#wp-dorna-fs-note').text('ارتباط با سرور برقرار نشد؛ دوباره تلاش می‌شود. سینک در پس‌زمینه ادامه دارد.');
        }).always(function () {
            busy = false;
        });
    }

    $(function () {
        $('#wp-dorna-fs [data-fs]').on('click', function () {
            var command = $(this).data('fs');
            if (command === 'cancel' && !window.confirm('سینک کامل لغو شود؟ کالاهایی که تا الان بروزرسانی شده‌اند می‌مانند.')) {
                return;
            }
            request(command);
        });

        render(state);
    });
})(jQuery);
