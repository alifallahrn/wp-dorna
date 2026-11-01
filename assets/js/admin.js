/* WP Dorna — admin behaviour. Vanilla JS, one module per screen. */
(function () {
    'use strict';

    var cfg = window.wpDorna;
    if (!cfg) {
        return;
    }

    var t = cfg.i18n;

    function fa(n) {
        return Number(n || 0).toLocaleString('fa-IR');
    }

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $all(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function sleep(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    /** POSTs to admin-ajax; resolves {ok, data}, rejects with a readable message. */
    function post(action, data) {
        var body = new FormData();
        body.append('action', 'wp_dorna_' + action);
        body.append('nonce', cfg.nonce);
        Object.keys(data || {}).forEach(function (key) { body.append(key, data[key]); });

        return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (json) { return { ok: !!json.success, data: json.data || {} }; })
            .catch(function () { throw new Error(t.networkError); });
    }

    function say(element, text, tone) {
        if (!element) {
            return;
        }
        element.hidden = !text;
        element.textContent = text || '';
        element.style.color = tone === 'bad' ? 'var(--bad)' : (tone === 'good' ? 'var(--good)' : '');
    }

    function setProgress(root, percent) {
        var bar = $('.wpd-progress', root);
        if (!bar) {
            return;
        }
        bar.setAttribute('aria-valuenow', percent);
        $('.wpd-progress__fill', bar).style.width = percent + '%';
        $('.wpd-progress__label', root).textContent = fa(percent) + '٪';
    }

    /* ───────────── full sync ───────────── */
    function fullSync() {
        var root = $('#wpd-fs');
        if (!root || !cfg.fullSync) {
            return;
        }

        var state = cfg.fullSync;
        var timer = null;
        var busy = false;

        var tones = { running: 'info', completed: 'good', paused: 'warn', failed: 'bad' };
        var visible = {
            start: ['idle', 'completed', 'cancelled'],
            pause: ['running'],
            resume: ['paused', 'failed'],
            cancel: ['running', 'paused', 'failed']
        };

        function eta(s) {
            if (s.status !== 'running' || !s.pages_left) {
                return '';
            }
            return 'زمان باقی‌مانده: حدود ' + fa(Math.max(1, Math.ceil(s.pages_left * (s.delay + 1) / 60))) + ' دقیقه';
        }

        function note(s) {
            var parts = [];
            if (s.status === 'running' && s.consecutive_errors > 0) {
                parts.push('درنا پاسخ نداد؛ بعد از چند لحظه دوباره تلاش می‌شود (' + fa(s.consecutive_errors) + ' از ۵).');
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
            $('#wpd-fs-status').textContent = t.states[s.status] || s.status;
            $('#wpd-fs-badge').className = 'wpd-badge is-' + (tones[s.status] || 'mut');
            $('#wpd-fs-eta').textContent = eta(s);
            setProgress(root, s.percent);

            ['checked', 'total', 'updated', 'unchanged', 'missing', 'failed'].forEach(function (key) {
                $('#wpd-fs-' + key).textContent = fa(s[key]);
            });

            say($('#wpd-fs-note'), note(s));

            $all('[data-fs]', root).forEach(function (button) {
                button.hidden = visible[button.getAttribute('data-fs')].indexOf(s.status) === -1;
            });

            var errors = $('#wpd-fs-errors');
            errors.textContent = '';
            (s.errors || []).forEach(function (line) {
                var li = document.createElement('li');
                li.textContent = line;
                errors.appendChild(li);
            });

            var report = $('#wpd-fs-report');
            report.hidden = !(s.status === 'completed' && s.not_in_dorna > 0);
            $('#wpd-fs-report-count').textContent = fa(s.not_in_dorna);
            $('#wpd-fs-report-list').value = (s.not_in_dorna_skus || []).join('\n');

            schedule();
        }

        function schedule() {
            if (state.status === 'running' && !timer) {
                // The server paces the calls itself; an early tick is a no-op.
                timer = setInterval(function () { request('tick'); }, Math.max(2, state.delay) * 1000);
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

            post('full_sync', { command: command }).then(function (result) {
                if (result.ok) {
                    state = result.data;
                    render(state);
                } else {
                    window.alert(result.data.message);
                }
            }).catch(function (error) {
                say($('#wpd-fs-note'), error.message + ' سینک در پس‌زمینه ادامه دارد.', 'bad');
            }).then(function () { busy = false; });
        }

        $all('[data-fs]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                var command = button.getAttribute('data-fs');
                if (command === 'cancel' && !window.confirm(t.confirmCancel)) {
                    return;
                }
                request(command);
            });
        });

        render(state);
    }

    /* ───────────── import ───────────── */
    function importer() {
        var root = $('#wpd-import');
        if (!root) {
            return;
        }

        var scanButton = $('[data-import="scan"]', root);
        var runButton = $('[data-import="run"]', root);
        var note = $('#wpd-import-note');
        var progress = $('#wpd-import-progress');
        var tableBox = $('#wpd-import-table');
        var rows = $('#wpd-import-rows');
        var empty = $('#wpd-import-empty');
        var products = [];
        var SHOWN = 200;

        function bar(percent) {
            progress.hidden = false;
            $('#wpd-import-fill').style.width = percent + '%';
            $('#wpd-import-percent').textContent = fa(percent) + '٪';
            $('.wpd-progress', progress).setAttribute('aria-valuenow', percent);
        }

        function cell(text) {
            var td = document.createElement('td');
            td.textContent = text;
            return td;
        }

        function drawRows() {
            rows.textContent = '';
            products.slice(0, SHOWN).forEach(function (product) {
                var tr = document.createElement('tr');
                var sku = cell(product.sku);
                sku.setAttribute('dir', 'ltr');
                sku.style.textAlign = 'right';
                tr.appendChild(cell(product.name));
                tr.appendChild(sku);
                tr.appendChild(cell(product.stock === null ? '—' : fa(product.stock)));
                var state = cell('در انتظار');
                state.setAttribute('data-sku-state', product.sku);
                tr.appendChild(state);
                rows.appendChild(tr);
            });

            if (products.length > SHOWN) {
                var tr = document.createElement('tr');
                var td = cell('و ' + fa(products.length - SHOWN) + ' کالای دیگر');
                td.colSpan = 4;
                tr.appendChild(td);
                rows.appendChild(tr);
            }
        }

        function setRowState(sku, text) {
            var el = $all('[data-sku-state]', rows).filter(function (node) {
                return node.getAttribute('data-sku-state') === sku;
            })[0];
            if (el) {
                el.textContent = text;
            }
        }

        async function scan() {
            scanButton.disabled = true;
            runButton.hidden = true;
            tableBox.hidden = true;
            empty.hidden = true;
            say(note, 'در حال بررسی کالاهای درنا…');

            var page = 1;
            var last = 1;

            try {
                while (page <= last) {
                    var result = await post('import_scan', { page: page });
                    if (!result.ok) {
                        say(note, result.data.message, 'bad');
                        scanButton.disabled = false;
                        return;
                    }
                    last = Math.max(1, result.data.last_page);
                    bar(Math.floor((page / last) * 100));
                    say(note, 'صفحه ' + fa(page) + ' از ' + fa(last) + ' — ' + fa(result.data.queued) + ' کالای جدید پیدا شد');
                    page++;
                    if (page <= last) {
                        await sleep(1500); // gentle on Dorna's server
                    }
                }

                var list = await post('import_list');
                products = list.data.products || [];
            } catch (error) {
                say(note, error.message, 'bad');
                scanButton.disabled = false;
                return;
            }

            scanButton.disabled = false;
            progress.hidden = true;

            if (!products.length) {
                say(note, '');
                empty.hidden = false;
                return;
            }

            say(note, fa(products.length) + ' کالای جدید آماده‌ی ورود است.');
            drawRows();
            tableBox.hidden = false;
            runButton.hidden = false;
        }

        async function run() {
            runButton.disabled = true;
            scanButton.disabled = true;
            var done = 0;
            var failed = 0;

            for (var i = 0; i < products.length; i++) {
                var product = products[i];
                setRowState(product.sku, 'در حال ورود…');
                try {
                    var result = await post('import_product', { sku: product.sku });
                    if (result.ok) {
                        done++;
                        setRowState(product.sku, 'وارد شد ✓');
                    } else {
                        failed++;
                        setRowState(product.sku, result.data.message);
                    }
                } catch (error) {
                    failed++;
                    setRowState(product.sku, error.message);
                }
                bar(Math.floor(((i + 1) / products.length) * 100));
            }

            say(note, fa(done) + ' کالا وارد شد' + (failed ? ' و ' + fa(failed) + ' مورد ناموفق بود.' : '.') + ' کالاها پیش‌نویس هستند؛ بعد از بررسی منتشر کنید.', failed ? '' : 'good');
            runButton.hidden = true;
            scanButton.disabled = false;
        }

        scanButton.addEventListener('click', scan);
        runButton.addEventListener('click', run);
    }

    /* ───────────── orders ───────────── */
    function orders() {
        function sendOne(id) {
            return post('send_order', { order_id: id });
        }

        $all('[data-orders="retry"]').forEach(function (button) {
            button.addEventListener('click', function () {
                var row = button.closest('tr');
                var cellState = $('[data-state]', row);
                button.disabled = true;
                button.textContent = t.sending;

                sendOne(button.getAttribute('data-order-id')).then(function (result) {
                    if (result.ok) {
                        window.location.reload();
                        return;
                    }
                    button.disabled = false;
                    button.textContent = 'ارسال دوباره';
                    var reason = document.createElement('span');
                    reason.className = 'wpd-sub';
                    reason.style.color = 'var(--bad)';
                    reason.textContent = result.data.message;
                    cellState.appendChild(reason);
                }).catch(function (error) {
                    button.disabled = false;
                    button.textContent = 'ارسال دوباره';
                    window.alert(error.message);
                });
            });
        });

        var all = $('[data-orders="retry-all"]');
        if (all) {
            all.addEventListener('click', async function () {
                var note = $('#wpd-orders-note');
                all.disabled = true;
                try {
                    var list = await post('unsent_orders');
                    var ids = list.data.ids || [];
                    var ok = 0;
                    for (var i = 0; i < ids.length; i++) {
                        say(note, 'در حال ارسال سفارش ' + fa(i + 1) + ' از ' + fa(ids.length) + '…');
                        var result = await sendOne(ids[i]);
                        ok += result.ok ? 1 : 0;
                        await sleep(1000); // one invoice per second
                    }
                    say(note, fa(ok) + ' از ' + fa(ids.length) + ' سفارش ثبت شد.', ok === ids.length ? 'good' : '');
                    await sleep(1200);
                    window.location.reload();
                } catch (error) {
                    say(note, error.message, 'bad');
                    all.disabled = false;
                }
            });
        }

        $all('[data-orders="send-box"]').forEach(function (button) {
            button.addEventListener('click', function () {
                var message = $('[data-box-message]', button.parentNode);
                button.disabled = true;
                button.textContent = t.sending;

                sendOne(button.getAttribute('data-order-id')).then(function (result) {
                    if (result.ok) {
                        window.location.reload();
                        return;
                    }
                    button.disabled = false;
                    button.textContent = t.send;
                    say(message, result.data.message, 'bad');
                }).catch(function (error) {
                    button.disabled = false;
                    button.textContent = t.send;
                    say(message, error.message, 'bad');
                });
            });
        });
    }

    /* ───────────── product, settings, logs ───────────── */
    function misc() {
        $all('[data-product-sync]').forEach(function (button) {
            button.addEventListener('click', function () {
                var message = $('[data-box-message]', button.parentNode);
                button.disabled = true;
                button.textContent = t.syncing;

                post('sync_product', { product_id: button.getAttribute('data-product-sync') }).then(function (result) {
                    button.disabled = false;
                    button.textContent = t.sync;
                    if (result.ok) {
                        window.location.reload();
                    } else {
                        say(message, result.data.message, 'bad');
                    }
                }).catch(function (error) {
                    button.disabled = false;
                    button.textContent = t.sync;
                    say(message, error.message, 'bad');
                });
            });
        });

        var test = $('[data-settings="test"]');
        if (test) {
            test.addEventListener('click', function () {
                var out = $('#wpd-test-result');
                test.disabled = true;
                say(out, 'در حال آزمایش…');

                post('test_connection').then(function (result) {
                    say(out, result.data.message, result.ok ? 'good' : 'bad');
                }).catch(function (error) {
                    say(out, error.message, 'bad');
                }).then(function () { test.disabled = false; });
            });
        }

        var clear = $('[data-logs="clear"]');
        if (clear) {
            clear.addEventListener('click', function () {
                if (!window.confirm(t.confirmClear)) {
                    return;
                }
                post('clear_logs').then(function () { window.location.reload(); });
            });
        }
    }

    /* ───────────── schedule, first-run checklist, confirmations ───────────── */
    function schedule() {
        $all('[data-schedule-form]').forEach(function (form) {
            var mode = $('[data-schedule="mode"]', form);
            var wrap = form.closest('.wpd-card') || form.parentNode;

            function toggle() {
                $('[data-schedule="weekday"]', form).hidden = mode.value !== 'weekly';
                $('[data-schedule="hour"]', form).hidden = mode.value === 'off';
            }

            mode.addEventListener('change', toggle);
            toggle();

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var button = $('button[type="submit"]', form);
                var note = $('[data-schedule="message"]', wrap);
                var data = {};
                $all('select', form).forEach(function (select) { data[select.name] = select.value; });

                button.disabled = true;
                post('save_schedule', data).then(function (result) {
                    if (!result.ok) {
                        say(note, result.data.message, 'bad');
                        return;
                    }
                    var text = result.data.mode === 'off' ? result.data.message : result.data.message + (result.data.next_due ? ' — اجرای بعدی: ' + result.data.next_due : '');
                    say(note, text, 'good');
                }).catch(function (error) {
                    say(note, error.message, 'bad');
                }).then(function () { button.disabled = false; });
            });
        });

        $all('[data-wizard-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                post('wizard_step', { step: button.getAttribute('data-wizard-step') }).then(function () { window.location.reload(); });
            });
        });

        $all('[data-wizard-finish]').forEach(function (button) {
            button.addEventListener('click', function () {
                post('wizard_finish').then(function (result) {
                    window.location.href = result.data.url || window.location.href;
                });
            });
        });

        $all('form[data-confirm="disconnect"]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(t.confirmDisconnect)) {
                    event.preventDefault();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        schedule();
        fullSync();
        importer();
        orders();
        misc();
    });
})();
