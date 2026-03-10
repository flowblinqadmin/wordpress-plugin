/* Flowblinq GEO — Admin JS */
/* global fqgeo, jQuery */

(function ($) {
    'use strict';

    var pollInterval = null;
    var pollCount = 0;
    var MAX_POLLS = 120; // 120 × 5s = 10 minutes
    var auditId = fqgeo.active_audit_id || null;

    // ── DOM refs ──────────────────────────────────────────────────────────────

    var $run     = $('#fqgeo-run');
    var $apply   = $('#fqgeo-apply');
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
                $apply.show();

                // If run 2 complete, show before/after
                if (data.free_run_number === 2 && data.scorecard) {
                    $apply.hide();
                    $verify.hide();
                    // Try to show comparison if we have snapshot
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
        $apply.hide();
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

            // Start polling every 5s
            stopPoller();
            pollInterval = setInterval(pollAudit, 5000);
        });
    });

    // ── Apply optimizations ───────────────────────────────────────────────────

    $apply.on('click', function () {
        $apply.prop('disabled', true);
        setStatus('Applying optimizations…');
        $progress.show();

        $.post(fqgeo.ajax_url, {
            action:   'fqgeo_apply',
            nonce:    fqgeo.nonce_apply,
            audit_id: auditId,
        }, function (resp) {
            $apply.prop('disabled', false);
            if (!resp.success) {
                setStatus('Apply failed: ' + (resp.data && resp.data.message ? resp.data.message : 'unknown'));
                return;
            }
            setStatus('Optimizations applied. You can now verify your changes.');
            $apply.hide();
            $verify.show();
            $progress.hide();
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

    // ── Auto-resume polling if page loaded with an active audit ──────────────

    if (auditId) {
        showProgress();
        setStatus('Checking audit status…', 5);
        pollAudit(); // immediate check
        pollInterval = setInterval(pollAudit, 5000);
    }

}(jQuery));
