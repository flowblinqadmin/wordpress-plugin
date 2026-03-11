/* Flowblinq GEO — Admin JS */
/* global fqgeo, jQuery */

(function ($) {
    'use strict';

    var pollInterval = null;
    var pollCount = 0;
    var MAX_POLLS = fqgeo.max_polls || 120;
    var auditId = fqgeo.active_audit_id || null;

    // ── DOM refs ──────────────────────────────────────────────────────────────

    var $run     = $('#fqgeo-run');
    var $verify  = $('#fqgeo-verify');
    var $progress = $('#fqgeo-progress');
    var $status  = $('#fqgeo-status');
    var $bar     = $('#fqgeo-bar');
    var $results = $('#fqgeo-results');
    var $scorecard = $('#fqgeo-scorecard');
    var $comparison = $('#fqgeo-comparison');
    var $baTable = $('#fqgeo-before-after tbody');

    // ── Helpers ───────────────────────────────────────────────────────────────

    function setStatus(msg, pct) {
        $status.text(msg);
        if (typeof pct === 'number') {
            $bar.val(pct);
        }
    }

    function showProgress() {
        $progress.show();
        $results.hide();
    }

    function renderScorecard(data) {
        var scorecard = data.scorecard || {};
        var score = data.overall_score !== null ? data.overall_score : '—';
        var issues = (scorecard.topIssues || []).slice(0, 5);
        var issueHtml = issues.length
            ? '<ul class="fqgeo-issues">' + issues.map(function (i) { return '<li>' + escHtml(i) + '</li>'; }).join('') + '</ul>'
            : '';

        $scorecard.html(
            '<h3>GEO Score</h3>' +
            '<div class="fqgeo-score">' + score + '<small>/100</small></div>' +
            '<div class="fqgeo-score-label">Overall Visibility Score</div>' +
            issueHtml
        );

        $results.show();
        $progress.hide();
    }

    function renderComparison(before, after) {
        if (!before || !after) { return; }
        var rows = [
            ['Overall Score', before.overallScore, after.overallScore],
        ];
        var html = rows.map(function (r) {
            return '<tr><td>' + escHtml(r[0]) + '</td><td>' + (r[1] !== undefined ? r[1] : '—') + '</td><td>' + (r[2] !== undefined ? r[2] : '—') + '</td></tr>';
        }).join('');
        $baTable.html(html);
        $comparison.show();
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function stopPoller() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        pollCount = 0;
    }

    // ── Status progress mapping ───────────────────────────────────────────────

    var statusProgress = {
        'pending':    10,
        'crawling':   25,
        'discovery':  40,
        'research':   60,
        'generating': 80,
        'complete':   100,
    };

    // ── Poll audit ────────────────────────────────────────────────────────────

    function pollAudit() {
        if (!auditId) { return; }

        pollCount++;
        if (pollCount > MAX_POLLS) {
            stopPoller();
            setStatus('Audit timed out. Please try again.');
            return;
        }

        $.post(fqgeo.ajax_url, {
            action:   'fqgeo_poll_audit',
            nonce:    fqgeo.nonce_poll,
            audit_id: auditId,
        }, function (resp) {
            if (!resp.success) {
                setStatus('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'unknown'));
                stopPoller();
                return;
            }

            var data = resp.data;
            var st = data.status;
            var pct = statusProgress[st] || 5;

            setStatus('Status: ' + st, pct);

            if (st === 'complete') {
                stopPoller();
                renderScorecard(data);

                if (data.slug) {
                    fqgeo.site_slug = data.slug;
                }

                // Show verify button directly (no Apply step)
                $verify.show();

                // If run 2 complete, show before/after
                if (data.free_run_number === 2 && data.scorecard) {
                    $verify.hide();
                    if (data.scorecard && data.scorecard._previousSnapshot) {
                        renderComparison(data.scorecard._previousSnapshot, data.scorecard);
                    }
                }
            }
        });
    }

    // ── Run audit ────────────────────────────────────────────────────────────

    $run.on('click', function () {
        $run.prop('disabled', true);
        $verify.hide();
        showProgress();
        setStatus('Submitting audit…', 5);

        $.post(fqgeo.ajax_url, {
            action: 'fqgeo_run_audit',
            nonce:  fqgeo.nonce_run,
        }, function (resp) {
            if (!resp.success) {
                setStatus('Failed: ' + (resp.data && resp.data.message ? resp.data.message : 'unknown'));
                $run.prop('disabled', false);
                return;
            }

            auditId = resp.data.audit_id;
            setStatus('Audit running…', statusProgress[resp.data.status] || 10);

            stopPoller();
            pollInterval = setInterval(pollAudit, 5000);
        });
    });

    // ── Verify changes ────────────────────────────────────────────────────────

    $verify.on('click', function () {
        $verify.prop('disabled', true);
        showProgress();
        setStatus('Triggering second audit…', 10);

        $.post(fqgeo.ajax_url, {
            action:   'fqgeo_verify',
            nonce:    fqgeo.nonce_verify,
            audit_id: auditId,
        }, function (resp) {
            $verify.prop('disabled', false);
            if (!resp.success) {
                setStatus('Verify failed: ' + (resp.data && resp.data.message ? resp.data.message : 'unknown'));
                return;
            }
            setStatus('Second audit running…', 10);
            stopPoller();
            pollInterval = setInterval(pollAudit, 5000);
        });
    });

    // ── Test connection ───────────────────────────────────────────────────────

    $('#fqgeo-test-connection').on('click', function () {
        var $btn = $(this);
        var $connStatus = $('#fqgeo-connection-status');
        $btn.prop('disabled', true);
        $connStatus.text('Testing…');

        $.post(fqgeo.ajax_url, {
            action: 'fqgeo_test_connection',
            nonce:  fqgeo.nonce_test,
        }, function (resp) {
            $btn.prop('disabled', false);
            if (resp.success) {
                $connStatus.text(resp.data.message).css('color', '#00a32a');
            } else {
                $connStatus.text(resp.data.message).css('color', '#d63638');
            }
        });
    });

    // ── Clear cache ───────────────────────────────────────────────────────────

    $('#fqgeo-clear-cache').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(fqgeo.ajax_url, {
            action: 'fqgeo_clear_cache',
            nonce:  fqgeo.nonce_clear,
        }, function (resp) {
            $btn.prop('disabled', false);
            if (resp.success) {
                alert(resp.data.message);
            }
        });
    });

    // ── Auto-resume polling if page loaded with an active audit ──────────────

    if (auditId) {
        showProgress();
        setStatus('Checking audit status…', 5);
        pollAudit();
        pollInterval = setInterval(pollAudit, 5000);
    }

}(jQuery));
