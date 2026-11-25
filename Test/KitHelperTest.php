<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\Runner;
use Kanboard\Core\Http\Response;
use Kanboard\Core\Plugin\Hook;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Session\SessionManager;
use Kanboard\Plugin\Harbor\Assets\ModuleLoader;
use Kanboard\Plugin\Harbor\Plugin;
use KanboardTests\units\Base;

class KitHelperTest extends Base
{
    public function testNonAdminGetsA403FromTheStyleGuide(): void
    {
        $response = $this->prepareStyleGuideRequest(Role::APP_USER);
        $response->expects($this->once())->method('html')->with(
            $this->stringContains('Access Forbidden'),
            403
        );

        (new Runner($this->container))->execute();
    }

    public function testAdminCanOpenTheStyleGuideFromTheSettingsSidebar(): void
    {
        $response = $this->prepareStyleGuideRequest(Role::APP_ADMIN);
        $response->expects($this->once())->method('html')->with($this->callback(function (string $html): bool {
            $this->assertStringContainsString('Style guide', $html);
            $this->assertMatchesRegularExpression('~<a\b[^>]*\bhref="[^"]*Harbor[^"]*"[^>]*>Style guide</a>~', $html);
            foreach (['pill', 'callout', 'progress', 'skeleton', 'empty', 'toolbar'] as $slot) {
                $this->assertStringContainsString('data-slot="' . $slot . '"', $html);
            }

            return true;
        }));

        (new Runner($this->container))->execute();
    }

    public function testTemplatesCanRenderAllSixKitSlots(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;

        $components = [
            'pill' => $kit->pill('Current', 'success'),
            'callout' => $kit->callout('Try again', 'The request failed.', 'error'),
            'progress' => $kit->progress(3, 5, 4.5, 8),
            'skeleton' => $kit->skeleton('row'),
            'empty' => $kit->empty('No matches', 'Try another word.', ''),
            'toolbar' => $kit->toolbar([]),
        ];

        foreach ($components as $slot => $html) {
            $this->assertStringContainsString('data-slot="' . $slot . '"', $html);
        }
    }

    public function testSubtaskProgressCombinesCompletionWithTheTimeBudget(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;

        $over = $kit->progress(4, 6, 9.2, 6);
        $this->assertStringContainsString('data-state="over" style="--pct:100%"', $over);
        $this->assertStringContainsString('4 of 6 subtasks', $over);
        $this->assertStringContainsString('9.2h / 6h · over budget', $over);

        $measured = $kit->progress(3, 5, 4.5, 8);
        $this->assertStringContainsString('data-state="measured" style="--pct:60%"', $measured);
        $this->assertStringContainsString('aria-valuenow="60"', $measured);
        $this->assertStringContainsString('data-state="done" style="--pct:100%"', $kit->progress(7, 7, 3, 4));
        $this->assertStringContainsString('data-state="measured" style="--pct:0%"', $kit->progress(0, 0, 0, 0));

        $loading = $kit->progress(0, null, 0, 0);
        $this->assertStringContainsString('data-state="indeterminate"', $loading);
        $this->assertStringNotContainsString('aria-valuenow', $loading);
    }

    public function testActivityRowRendersInitialsTrustedSentenceAndMachineReadableTime(): void
    {
        (new Plugin($this->container))->initialize();
        $sentence = '<strong>Élodie Santana</strong> moved <a href="?task_id=42">#42</a>.';
        $timestamp = strtotime('2026-11-20T14:30:00+00:00');
        $html = $this->container['template']->kit->activityRow('Élodie Santana', $sentence, $timestamp, 'event-42');

        $this->assertStringContainsString('data-slot="activity-row" id="event-42"', $html);
        $this->assertMatchesRegularExpression('~>ÉS</span>\s*<div[^>]*>'
            . preg_quote($sentence, '~') . '</div>\s*<time datetime="'
            . preg_quote(date(DATE_ATOM, $timestamp), '~') . '">[^<]+</time>~u', $html);
    }

    public function testHeatmapRendersEveryDayAndQuantisesCountsToFiveLevels(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $today = new DateTimeImmutable('today');
        $counts = [];
        foreach ([1, 3, 5, 8] as $daysAgo => $count) {
            $date = $today->modify("-$daysAgo days")->format('Y-m-d');
            $counts[$date] = $count;
        }
        $counts[$today->modify('-14 days')->format('Y-m-d')] = 500;
        $html = $kit->heatmap($counts, 2);

        $this->assertStringContainsString('data-slot="heatmap"', $html);
        preg_match_all('/style="--level:(\d+)" aria-label="(\d+) events on (\d{4}-\d{2}-\d{2})"/', $html, $cells, PREG_SET_ORDER);
        $expectedLevels = [8 => 4, 5 => 3, 3 => 2, 1 => 1, 0 => 0];
        $this->assertCount(14, $cells);
        foreach ($cells as $index => [, $level, $count, $date]) {
            $this->assertSame($today->modify(($index - 13) . ' days')->format('Y-m-d'), $date);
            $this->assertSame($counts[$date] ?? 0, (int) $count);
            $this->assertSame($expectedLevels[$count], (int) $level);
        }
        $this->assertSame(182, substr_count($kit->heatmap([]), 'style="--level:0"'));
    }

    public function testDeepLinkedCommentsAndActivityRowsUseTheHighlightToken(): void
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/kit.css');
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        foreach (['.comment:target', '[data-slot="activity-row"]:target'] as $selector) {
            $declarations = '';
            foreach ($rules as [, $selectors, $body]) {
                if (in_array($selector, array_map('trim', explode(',', $selectors)), true)) {
                    $declarations .= $body;
                }
            }
            $this->assertMatchesRegularExpression('/background(?:-color)?:\s*var\(--highlight,/', $declarations);
            $this->assertDoesNotMatchRegularExpression('/var\(--(?:accent|muted)[,)]/', $declarations);
        }
    }

    public function testActivityModuleStylesCoreEventsAndUsesPrimaryHeatmapSteps(): void
    {
        $file = __DIR__ . '/../Assets/css/kit-activity.css';
        $this->assertFileExists($file);
        $css = file_get_contents($file);

        $this->assertStringContainsString('.activity-event', $css);
        $this->assertMatchesRegularExpression('/\[data-slot="heatmap"\]\s*\{[^}]*grid-template-rows:\s*repeat\(7,/', $css);
        $this->assertMatchesRegularExpression('/color-mix\([^;]*var\(--primary,[^;]*var\(--level/', $css);
        $this->assertStringNotContainsString('--pulse-', $css);
    }

    public function testTypedConfirmRendersADialogWithAnInitiallyDisabledSubmit(): void
    {
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->kit->typedConfirm(
            'Delete & archive',
            '<p>This removes the project.</p>',
            'delete & archive',
            'Delete project',
            '/?controller=ProjectController&action=remove'
        );

        $this->assertMatchesRegularExpression('/<dialog\b[^>]*data-slot="typed-confirm"/', $html);
        $this->assertStringContainsString('Delete &amp; archive', $html);
        $this->assertStringContainsString('<p>This removes the project.</p>', $html);
        $this->assertStringContainsString('Type delete &amp; archive to confirm', $html);
        $this->assertStringContainsString('data-phrase="delete &amp; archive"', $html);
        $this->assertStringContainsString('method="post" action="/?controller=ProjectController&amp;action=remove"', $html);
        $this->assertSame(1, preg_match('/<input type="hidden" name="csrf_token" value="([^"]+)"/', $html, $token));
        $this->assertTrue($this->container['token']->validateCSRFToken($token[1]));
        $this->assertMatchesRegularExpression('/<button\b(?=[^>]*\btype="submit")(?=[^>]*\sdisabled\b)[^>]*>\s*Delete project\s*<\/button>/', $html);
    }

    public function testStageUsesDarkTokensInLightMode(): void
    {
        $file = __DIR__ . '/../Assets/css/kit-float.css';
        $this->assertFileExists($file);
        $this->assertMatchesRegularExpression(
            '/html\.theme-light\s+\[data-slot="stage"\]\s*\{[^}]*color-scheme:\s*dark;[^}]*--background:\s*var\(--stage-dark-background,/',
            file_get_contents($file)
        );
    }

    public function testChatMessageRendersToolChipsBeforeTheBodyAndIdentifiesTheSpeaker(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $body = '<p>Found <strong>seven</strong> plugins.</p>';
        $other = $kit->chatMessage('Agent & reviewer', $body, '10:41', false, ['Search <assets>', 'Read files']);

        $this->assertStringContainsString('data-slot="chat-message" data-from="other"', $other);
        $this->assertStringContainsString('Agent &amp; reviewer', $other);
        $this->assertStringContainsString('10:41', $other);
        $this->assertMatchesRegularExpression(
            '~data-slot="tool-chip"[^>]*>\s*Search &lt;assets&gt;\s*</span>\s*'
            . '<span\b[^>]*data-slot="tool-chip"[^>]*>\s*Read files\s*</span>.*'
            . preg_quote($body, '~') . '~s',
            $other
        );

        $self = $kit->chatMessage('Me', '<p>Thanks!</p>', '10:42', true);
        $this->assertStringContainsString('data-slot="chat-message" data-from="self"', $self);
        $this->assertStringNotContainsString('data-slot="tool-chip"', $self);
    }

    public function testComposerPostsTheMessageAndCsrfTokenFromAFloatingPill(): void
    {
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->kit->composer('Message "Design"', '/?controller=Chat&action=send');

        $this->assertMatchesRegularExpression('~<form\b[^>]*data-slot="composer"[^>]*data-kind="pill"~', $html);
        $this->assertStringContainsString('method="post" action="/?controller=Chat&amp;action=send"', $html);
        $this->assertMatchesRegularExpression('~<textarea\b[^>]*name="message"[^>]*placeholder="Message &quot;Design&quot;"~', $html);
        $this->assertSame(1, preg_match('/<input type="hidden" name="csrf_token" value="([^"]+)"/', $html, $token));
        $this->assertTrue($this->container['token']->validateCSRFToken($token[1]));
        $this->assertMatchesRegularExpression('~<button\b[^>]*type="submit"~', $html);
    }

    public function testChatLoadsTheFloatModuleOnceAndOnlyOnRoutesRequestingChat(): void
    {
        foreach (['ChatController', 'DashboardController'] as $controller) {
            $hook = new Hook();
            $hook->on('kit:modules', static fn (string $route): array => $route === 'ChatController' ? ['chat'] : []);
            $hook->on('kit:modules', static fn (string $route): array => $route === 'ChatController' ? ['float', 'chat'] : []);

            (new ModuleLoader($hook, $this->container['logger']))->register($controller, null);

            foreach (['css', 'js'] as $type) {
                $expected = $controller === 'ChatController' ? [
                    "plugins/Harbor/Assets/$type/kit-float.$type",
                    "plugins/Harbor/Assets/$type/kit-chat.$type",
                ] : [];
                $this->assertSame($expected, array_column($hook->getListeners('template:layout:' . $type), 'template'));
            }
        }
    }

    public function testStatTilePrintsItsValueAndOptionallyCarriesSparklinePoints(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $html = $kit->statTile('CPU <idle>', '37%', [12, 44.5, 37]);
        $this->assertStringContainsString('data-slot="stat-tile"', $html);
        $this->assertStringContainsString('CPU &lt;idle&gt;', $html);
        $this->assertMatchesRegularExpression('~<strong>37%</strong>~', $html);
        $this->assertSame(1, preg_match('/data-slot="sparkline" data-points="([^"]+)"/', $html, $matches));
        $this->assertSame([12, 44.5, 37], json_decode(html_entity_decode($matches[1]), true, 512, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('data-slot="sparkline"', $kit->statTile('Uptime', '18d'));
    }

    public function testLineChartSharesOrderedSeriesAndValuesBetweenSvgDataAndTheFallbackTable(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $series = [
            ['name' => 'User & system', 'values' => [12, 18.5]],
            ['name' => 'Idle', 'values' => [88, 81.5]],
            ['name' => 'I/O', 'values' => [0, 1]],
            ['name' => 'IRQ', 'values' => [2, 3]],
            ['name' => 'Steal', 'values' => [4, 5]],
        ];
        $html = $kit->lineChart($series, ['12:00 <UTC>', '12:05']);

        $this->assertStringContainsString('data-slot="line-chart"', $html);
        $this->assertSame(1, preg_match('/data-series="([^"]+)"/', $html, $matches));
        $data = json_decode(html_entity_decode($matches[1]), true, 512, JSON_THROW_ON_ERROR);
        foreach ($series as $index => $entry) {
            $this->assertSame($entry + ['color' => '--chart-' . ($index + 1)], $data[$index]);
        }
        $this->assertStringContainsString('data-slot="chart-legend"', $html);
        $this->assertMatchesRegularExpression('~<table data-slot="chart-table">.*'
            . '<th scope="col">User &amp; system</th>.*'
            . '<th scope="row">12:00 &lt;UTC&gt;</th>\s*<td>12</td>\s*<td>88</td>\s*<td>0</td>\s*<td>2</td>\s*<td>4</td>.*'
            . '<th scope="row">12:05</th>\s*<td>18\.5</td>\s*<td>81\.5</td>\s*<td>1</td>\s*<td>3</td>\s*<td>5</td>.*</table>~s', $html);

        $single = $kit->lineChart([$series[0]], ['12:00', '12:05', '12:10']);
        $this->assertStringNotContainsString('data-slot="chart-legend"', $single);
        $this->assertStringContainsString('<table data-slot="chart-table">', $single);
        $this->assertMatchesRegularExpression('~<th scope="row">12:10</th>\s*<td>—</td>~', $single);
        $this->assertStringContainsString('<table data-slot="chart-table">', $kit->lineChart([], []));

        $this->expectException(\InvalidArgumentException::class);
        $kit->lineChart(array_fill(0, 6, $series[0]), ['12:00', '12:05']);
    }

    public function testUsageBarUsesStrictThresholdsAndPrintsCapacity(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        foreach ([80 => 'normal', 81 => 'warning', 90 => 'warning', 91 => 'danger', 120 => 'danger'] as $used => $level) {
            $html = $kit->usageBar('Disk & pool', $used, 100, 'GB');
            $this->assertStringContainsString('data-slot="usage-bar"', $html);
            $this->assertStringContainsString('data-level="' . $level . '"', $html);
            $this->assertStringContainsString('style="--pct:' . min(100, $used) . '%"', $html);
            $this->assertStringContainsString('Disk &amp; pool', $html);
            $this->assertStringContainsString($used . ' / 100 GB', $html);
        }
        $this->assertStringContainsString('style="--pct:0%"', $kit->usageBar('Disk', 0, 0, 'GB'));
    }

    public function testStatusAlwaysRendersAnIconAndTheFrozenWord(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        foreach ([
            'running' => 'Running',
            'stopped' => 'Stopped',
            'starting' => 'Starting',
            'failed' => 'Failed',
            'degraded' => 'Degraded',
            'pending' => 'Pending approval',
        ] as $state => $word) {
            $html = $kit->status($state);
            $this->assertMatchesRegularExpression(
                '~<span data-slot="status" data-state="' . $state . '">\s*'
                . '<span aria-hidden="true">\S+</span>\s*' . $word . '\s*</span>~u',
                $html
            );
        }

        $this->expectException(InvalidArgumentException::class);
        $kit->status('unknown');
    }

    public function testDataTableReusesTheToolbarAndKeepsThePagerOutsideTheScrollWrapper(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $html = $kit->dataTable(
            ['actor' => ['label' => 'Actor', 'sortUrl' => '?sort=actor&order=desc', 'sort' => 'ascending'], 'outcome' => 'Outcome'],
            [['outcome' => 'Allowed', 'actor' => 'A & B']],
            ['start' => 1, 'end' => 50, 'total' => 2314, 'previous' => '?page=1', 'next' => '?page=3', 'toolbar' => ['query' => 'audit']]
        );

        $this->assertStringContainsString('data-slot="data-table"', $html);
        $this->assertStringContainsString($kit->toolbar(['query' => 'audit']), $html);
        $this->assertMatchesRegularExpression('~<div class="table-wrap"[^>]*tabindex="0"[^>]*>\s*<table>.*</table>\s*</div>.*1–50 of 2,314~s', $html);
        $this->assertMatchesRegularExpression('/\.table-wrap\s*\{[^}]*overflow-x:\s*auto/', file_get_contents(__DIR__ . '/../Assets/css/kit.css'));
        $this->assertMatchesRegularExpression('~<th scope="col" aria-sort="ascending">\s*<a href="\?sort=actor&amp;order=desc">Actor</a>~', $html);
        $this->assertMatchesRegularExpression('~<td>A &amp; B</td>\s*<td>Allowed</td>~', $html);
        $this->assertStringContainsString('<a href="?page=1" rel="prev">Previous</a>', $html);
        $this->assertStringContainsString('<a href="?page=3" rel="next">Next</a>', $html);

        $empty = $kit->dataTable(['actor' => 'Actor'], [], ['start' => 0, 'end' => 0, 'total' => 0, 'next' => '//example.com']);
        $this->assertStringNotContainsString('href=', $empty);
    }

    public function testStepListShowsEveryStateAndOnlyFailedStepsHaveARetrySlot(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $retry = '<button type="submit">Retry</button>';
        $html = $kit->stepList([
            ['label' => 'Snapshot', 'state' => 'done', 'duration' => '0.4 s'],
            ['label' => 'Upload', 'state' => 'active', 'detail' => '63%'],
            ['label' => 'Prune', 'state' => 'waiting', 'detail' => 'Queued'],
            ['label' => 'Notify', 'state' => 'failed', 'detail' => '401 <Resend>', 'retry' => static fn () => $retry],
        ]);

        $this->assertStringContainsString('<ol data-slot="step-list">', $html);
        foreach (['done' => '0.4 s', 'active' => '63%', 'waiting' => 'Queued', 'failed' => '401 &lt;Resend&gt;'] as $state => $detail) {
            $this->assertMatchesRegularExpression(
                '~<li data-slot="step" data-state="' . $state . '">(?:(?!</li>).)*' . preg_quote($detail, '~') . '~s',
                $html
            );
        }
        $this->assertSame(1, substr_count($html, 'data-slot="retry"'));
        $this->assertMatchesRegularExpression(
            '~data-state="failed">(?:(?!</li>).)*<span data-slot="retry">\s*' . preg_quote($retry, '~') . '~s',
            $html
        );
        $this->assertStringContainsString('data-slot="retry"', $kit->stepList([['label' => 'Notify', 'state' => 'failed']]));
    }

    public function testServiceTileComposesTheStatusBadgeWithoutStateSpecificTileMarkup(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $html = $kit->serviceTile('SQL & backups', 'degraded', 'Disk < 10 GB free');
        $badge = trim($kit->status('degraded'));

        $this->assertStringContainsString('data-slot="service-tile"', $html);
        $this->assertStringContainsString('SQL &amp; backups', $html);
        $this->assertStringContainsString('Disk &lt; 10 GB free', $html);
        $this->assertSame(1, substr_count($html, $badge));
        $frame = str_replace($badge, '', $html);
        $this->assertDoesNotMatchRegularExpression('/\b(?:style|data-state|data-tone)\s*=|degraded/i', $frame);
        $runningFrame = str_replace(trim($kit->status('running')), '', $kit->serviceTile('SQL & backups', 'running', 'Disk < 10 GB free'));
        $this->assertSame($frame, $runningFrame);
    }

    public function testLogViewerRendersAnAccessibleLogAndFollowControls(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $html = $kit->logViewer('Backup <output>', '/logs?id=14&tail=1');

        $this->assertStringContainsString('data-slot="log-viewer"', $html);
        $this->assertStringContainsString('data-sse-url="/logs?id=14&amp;tail=1"', $html);
        $this->assertStringContainsString('Backup &lt;output&gt;', $html);
        $this->assertMatchesRegularExpression('~<[^>]+role="log"[^>]+aria-live="off"[^>]*>~', $html);
        $this->assertStringContainsString('0 lines', $html);
        foreach (['Follow', 'Pause', 'Jump to latest'] as $label) {
            $this->assertMatchesRegularExpression('~<button\b[^>]*>' . $label . '</button>~', $html);
        }
    }

    public function testApprovalCardRendersTheDeadlineAndDisablesAnExpiredAction(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $deadline = time() + 300;
        $details = ['Target' => 'backup <latest>'];
        $html = $kit->approvalCard($deadline, $details, '/approve?id=14', 'Open approval');

        $this->assertStringContainsString('data-slot="approval-card"', $html);
        $this->assertStringContainsString('data-expires-at="' . $deadline . '"', $html);
        $this->assertStringContainsString('Pending approval', $html);
        $this->assertStringContainsString('backup &lt;latest&gt;', $html);
        $this->assertStringContainsString('href="/approve?id=14"', $html);
        $expired = $kit->approvalCard(time() - 1, $details, '/approve?id=14', 'Open approval');
        $this->assertStringContainsString('Expired', $expired);
        $this->assertStringContainsString('aria-disabled="true"', $expired);
        $this->assertStringNotContainsString('href=', $expired);
    }

    public function testBreadcrumbsMarkOnlyTheLastItemAsTheCurrentPage(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $html = $kit->breadcrumbs([
            ['label' => 'Projects', 'href' => '?controller=ProjectListController'],
            ['label' => 'Design & QA', 'href' => '/projects/94'],
            ['label' => 'Audit', 'href' => '/projects/94/audit'],
        ]);

        $this->assertStringContainsString('data-slot="breadcrumbs"', $html);
        $this->assertMatchesRegularExpression('~<a href="\?controller=ProjectListController">Projects</a>~', $html);
        $this->assertStringContainsString('Design &amp; QA', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('~<span aria-current="page">Audit</span>~', $html);
        $this->assertStringNotContainsString('href="/projects/94/audit"', $html);
        $this->assertSame('', $kit->breadcrumbs([]));
    }

    public function testDateRangeRendersFrozenPresetsAndAClosedCustomPopover(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $html = $kit->dateRange('2026-11-19T00:00:00Z', '2026-12-03T23:59:59Z');

        $this->assertStringContainsString('data-slot="date-range"', $html);
        $this->assertStringContainsString('data-slot="segmented"', $html);
        preg_match_all('~<button[^>]*>(15m|1h|24h|7d|Custom…)</button>~', $html, $matches);
        $this->assertSame(['15m', '1h', '24h', '7d', 'Custom…'], $matches[1]);
        $this->assertStringContainsString('data-from="2026-11-19T00:00:00Z"', $html);
        $this->assertStringContainsString('data-to="2026-12-03T23:59:59Z"', $html);
        $this->assertMatchesRegularExpression('~aria-expanded="false" aria-controls="([^"]+)".*id="\1"[^>]*hidden~s', $html);
        $subset = $kit->dateRange(null, null, ['1h']);
        $this->assertStringContainsString('>1h</button>', $subset);
        $this->assertStringNotContainsString('>15m</button>', $subset);
    }

    public function testAppShellStylesTheCoreHeaderAndViewSwitcherWithoutAnOverride(): void
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/kit.css');
        $this->assertMatchesRegularExpression('~html body > header\s*\{[^}]*border-bottom:~', $css);
        $this->assertMatchesRegularExpression('~\.project-header\s*\{[^}]*border-bottom:~', $css);
        $this->assertMatchesRegularExpression('~\.views-switcher[^ ,{]*[^{}]*\.views\s*\{[^}]*overflow-x:\s*auto~', $css);

        $template = $this->container['template'];
        $core = [$template->getTemplateFile('header'), $template->getTemplateFile('project_header/views')];
        (new Plugin($this->container))->initialize();
        $this->assertSame($core, [$template->getTemplateFile('header'), $template->getTemplateFile('project_header/views')]);
    }

    public function testKitEscapesUserControlledTextAndAttributes(): void
    {
        (new Plugin($this->container))->initialize();
        $kit = $this->container['template']->kit;
        $input = '"><img src=x onerror=alert(1)>';
        $escaped = '&quot;&gt;&lt;img src=x onerror=alert(1)&gt;';

        $components = [
            [$kit->pill($input, $input), 2],
            [$kit->callout($input, $input, $input), 3],
            [$kit->empty($input, $input, $input), 3],
            [$kit->toolbar([
                'query' => $input,
                'searchName' => $input,
                'searchLabel' => $input,
                'submitLabel' => $input,
                'params' => [$input => $input],
                'selects' => [['name' => $input, 'label' => $input, 'value' => $input, 'options' => [$input => $input]]],
                'chips' => [['name' => $input, 'label' => $input, 'value' => $input, 'checked' => true]],
                'resultCount' => $input,
            ]), 15],
        ];

        foreach ($components as [$html, $count]) {
            $this->assertStringNotContainsString('<img', $html);
            $this->assertSame($count, substr_count($html, $escaped));
        }

        $this->assertStringContainsString('data-kind="row"', $kit->skeleton($input));
        $button = '<button type="button">Clear filters</button>';
        $this->assertStringContainsString($button, $kit->empty('No matches', 'Try another word.', static fn () => $button));
    }

    public function testKitStylesExposeSlotsAndPopupStates(): void
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/kit.css');

        foreach (['pill', 'callout', 'progress', 'skeleton', 'empty', 'toolbar', 'chip', 'result-count',
            'control', 'tabs', 'segmented', 'popup', 'toaster', 'toast'] as $slot) {
            $this->assertStringContainsString('[data-slot="' . $slot . '"]', $css);
        }
        foreach (['menu', 'popover', 'dialog', 'tooltip'] as $preset) {
            $this->assertStringContainsString('[data-popup-preset="' . $preset . '"]', $css);
        }
        $this->assertStringContainsString('left: var(--popup-x', $css);
        $this->assertStringContainsString('top: var(--popup-y', $css);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce[^}]+\[data-slot="skeleton"\][^}]+animation: none/s', $css);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce[^}]+data-starting-style[^}]+animation: none/s', $css);
    }

    public function testLoadingAndPopupMotionPreserveLayout(): void
    {
        $css = file_get_contents(__DIR__ . '/../Assets/css/kit.css');

        $this->assertMatchesRegularExpression('/\[data-slot="skeleton"\]\s*\{[^}]*animation: harbor-skeleton-reveal 300ms steps\(1, end\)/', $css);
        $this->assertMatchesRegularExpression('/@keyframes harbor-popup-in\s*\{[^}]*\}[^}]*to\s*\{[^}]*transform: none/', $css);
    }

    public function testLayoutRendersOneEmptyPoliteToasterInsideTheBody(): void
    {
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->render('layout', [
            'no_layout' => true,
            'content_for_layout' => '<main>Page content</main>',
        ]);

        $toaster = '<div data-slot="toaster" role="status" aria-live="polite"></div>';
        $this->assertSame(1, substr_count($html, $toaster));
        $this->assertMatchesRegularExpression('~<body\b.*' . preg_quote($toaster, '~') . '\s*</body>~s', $html);
    }

    private function prepareStyleGuideRequest(string $role): Response
    {
        (new Plugin($this->container))->initialize();
        $userId = $role === Role::APP_ADMIN ? 1 : $this->container['userModel']->create([
            'username' => 'kit-member',
            'role' => $role,
        ]);
        $user = $this->container['userModel']->getById($userId);
        $this->container['userSession']->initialize($user);
        $this->container['sessionManager'] = $this->createMock(SessionManager::class);
        $this->container['request']->setParams([
            'controller' => 'HarborStyleGuideController',
            'action' => 'show',
            'plugin' => 'Harbor',
        ]);
        $this->container['router']->dispatch();

        $response = $this->createMock(Response::class);
        $this->container['response'] = $response;

        return $response;
    }
}
