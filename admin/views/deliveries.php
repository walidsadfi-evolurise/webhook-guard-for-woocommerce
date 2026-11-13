<?php
/**
 * Deliveries tab.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

$wcwg_counts = WCWG_Store::counts( 24 );
$wcwg_table  = new WCWG_List_Table();
$wcwg_table->prepare_items();
?>
<style>
	.wcwg-cards{display:flex;gap:12px;margin:16px 0}
	.wcwg-card{background:#fff;border:1px solid #c3c4c7;padding:12px 16px;min-width:140px}
	.wcwg-card strong{display:block;font-size:22px}
	.wcwg-status{padding:2px 8px;border-radius:3px;font-weight:600}
	.wcwg-status-success{background:#edfaef;color:#1b7a2b}
	.wcwg-status-failed{background:#fff8e5;color:#8a6d00}
	.wcwg-status-dead{background:#fcf0f1;color:#b32d2e}
	.wcwg-status-replayed{background:#f0f0f1;color:#50575e}
</style>
<div class="wcwg-cards">
	<div class="wcwg-card"><strong><?php echo (int) $wcwg_counts['success']; ?></strong><?php esc_html_e( 'succeeded (24 h)', 'webhook-guard-for-woocommerce' ); ?></div>
	<div class="wcwg-card"><strong><?php echo (int) $wcwg_counts['failed']; ?></strong><?php esc_html_e( 'retrying', 'webhook-guard-for-woocommerce' ); ?></div>
	<div class="wcwg-card"><strong><?php echo (int) $wcwg_counts['dead']; ?></strong><?php esc_html_e( 'dead (24 h)', 'webhook-guard-for-woocommerce' ); ?></div>
	<div class="wcwg-card"><strong><?php echo (int) $wcwg_counts['replayed']; ?></strong><?php esc_html_e( 'replayed (24 h)', 'webhook-guard-for-woocommerce' ); ?></div>
</div>
<form method="get">
	<input type="hidden" name="page" value="wcwg">
	<?php if ( isset( $_GET['webhook_id'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<input type="hidden" name="webhook_id" value="<?php echo (int) $_GET['webhook_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
	<?php endif; ?>
	<?php $wcwg_table->views(); ?>
	<?php $wcwg_table->search_box( __( 'Search deliveries', 'webhook-guard-for-woocommerce' ), 'wcwg' ); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'wcwg_action' ); ?>
	<input type="hidden" name="action" value="wcwg_action">
	<input type="hidden" name="do" value="bulk_replay">
	<?php $wcwg_table->display(); ?>
</form>
<script>
document.addEventListener('click',function(e){var t=e.target.closest('.wcwg-toggle-body');if(!t){return;}e.preventDefault();var p=document.getElementById('wcwg-body-'+t.dataset.id);if(p){p.style.display=p.style.display==='none'?'block':'none';}});
</script>
