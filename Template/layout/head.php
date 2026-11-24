<?php
/* No-FOUC theme preload. MUST be an external, BLOCKING script in <head> (no
 * defer/async) so it runs before first paint — Kanboard's CSP blocks inline
 * <script>, and $this->asset->js() adds `defer` (too late). filemtime busts the
 * cache when the file changes. */
$shadcnPreloadFile = dirname(__DIR__, 2) . '/Assets/js/theme-preload.js';
?>
<script src="<?= $this->url->dir() ?>plugins/Harbor/Assets/js/theme-preload.js?<?= @filemtime($shadcnPreloadFile) ?>"></script>
