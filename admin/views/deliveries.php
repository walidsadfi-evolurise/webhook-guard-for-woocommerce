<?php
/**
 * Deliveries tab.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

$evowg_counts = EVOWG_Store::counts( 24 );
$evowg_table  = new EVOWG_List_Table();
$evowg_table->prepare_items();
?>
<div class="evowg-cards">
	<div class="evowg-card"><strong><?php echo (int) $evowg_counts['success']; ?></strong><?php esc_html_e( 'succeeded (24 h)', 'evolurise-webhook-guard-for-woocommerce' ); ?></div>
	<div class="evowg-card"><strong><?php echo (int) $evowg_counts['failed']; ?></strong><?php esc_html_e( 'retrying', 'evolurise-webhook-guard-for-woocommerce' ); ?></div>
	<div class="evowg-card"><strong><?php echo (int) $evowg_counts['dead']; ?></strong><?php esc_html_e( 'dead (24 h)', 'evolurise-webhook-guard-for-woocommerce' ); ?></div>
	<div class="evowg-card"><strong><?php echo (int) $evowg_counts['replayed']; ?></strong><?php esc_html_e( 'replayed (24 h)', 'evolurise-webhook-guard-for-woocommerce' ); ?></div>
</div>
<form method="get">
	<input type="hidden" name="page" value="evowg">
	<?php if ( isset( $_GET['webhook_id'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<input type="hidden" name="webhook_id" value="<?php echo (int) $_GET['webhook_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
	<?php endif; ?>
	<?php $evowg_table->views(); ?>
	<?php $evowg_table->search_box( __( 'Search deliveries', 'evolurise-webhook-guard-for-woocommerce' ), 'evowg' ); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'evowg_action' ); ?>
	<input type="hidden" name="action" value="evowg_action">
	<input type="hidden" name="do" value="bulk_replay">
	<?php $evowg_table->display(); ?>
</form>
