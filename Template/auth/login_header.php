<?php
// Branding is Steward's (1.2.0): its logo or site name when it is installed, the product name otherwise.
try {
    $shadcnBranding = $this->helper->stewardBranding;
} catch (\InvalidArgumentException $e) {
    $shadcnBranding = null;
}
$shadcnLogoUrl = $shadcnBranding ? $shadcnBranding->logoUrl() : '';
$shadcnSiteName = ($shadcnBranding ? $shadcnBranding->siteName() : '') ?: 'Kanboard';
?>
<div class="auth-card-header">
<?php if ($shadcnLogoUrl !== ''): ?>
    <img src="<?= $shadcnLogoUrl ?>"
         alt="<?= $this->text->e($shadcnSiteName) ?>"
         class="auth-card-logo">
<?php else: ?>
    <h1 class="auth-card-title"><?= $this->text->e($shadcnSiteName) ?></h1>
<?php endif ?>
    <p class="auth-card-description"><?= t('Sign in to your account') ?></p>
</div>
