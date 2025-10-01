<?php
/**
 * Plugin Name: Required Taxonomies
 * Plugin URI:  https://example.com/
 * Description: Force users to assign at least one term for selected taxonomies per post type in both the block and classic editors.
 * Version:     1.0.0
 * Author:      OpenAI Assistant
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: required-taxonomies
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( ! defined( 'SK_TAX_REQ_OPTION' ) ) {
define( 'SK_TAX_REQ_OPTION', 'sk_tax_required_matrix' );
}

/* ----------------------------------------------------------------------------
 * Settings page (matrix)
 * ------------------------------------------------------------------------- */

add_action( 'admin_menu', 'sk_tax_req_register_settings_page' );

function sk_tax_req_register_settings_page() {
add_options_page(
__( 'Required Taxonomies', 'required-taxonomies' ),
__( 'Required Taxonomies', 'required-taxonomies' ),
'manage_options',
'sk-tax-required',
'sk_tax_req_render_settings_page'
);
}

function sk_tax_req_render_settings_page() {
if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
sk_tax_req_handle_settings_form();
}

$required = get_option( SK_TAX_REQ_OPTION, array() );
$pt_objs  = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
$tx_objs  = get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'objects' );

unset( $pt_objs['attachment'], $pt_objs['nav_menu_item'], $pt_objs['revision'], $pt_objs['custom_css'], $pt_objs['customize_changeset'], $pt_objs['wp_block'] );
unset( $tx_objs['post_format'], $tx_objs['nav_menu'], $tx_objs['link_category'] );

echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'Required taxonomies per post type', 'required-taxonomies' ) . '</h1>';

if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'required-taxonomies' ) . '</p></div>';
}

echo '<form method="post">';
wp_nonce_field( 'sk_tax_req_save', 'sk_tax_req_nonce' );

echo '<p>' . esc_html__( 'Select which taxonomies are required per post type (at least one term is needed to publish).', 'required-taxonomies' ) . '</p>';
echo '<p><input type="text" id="sk-matrix-search" placeholder="' . esc_attr__( 'Search post type or taxonomy…', 'required-taxonomies' ) . '" style="max-width:280px;padding:4px 8px" /></p>';

echo '<table class="widefat striped" id="sk-matrix">';
echo '<thead><tr><th style="min-width:220px">' . esc_html__( 'Post Type \\ Taxonomy', 'required-taxonomies' ) . '</th>';

foreach ( $tx_objs as $tx => $txo ) {
echo '<th data-tax="' . esc_attr( $tx ) . '" style="text-align:center;white-space:nowrap">' . esc_html( $txo->labels->name ) . '<br><code>' . esc_html( $tx ) . '</code></th>';
}

echo '</tr></thead><tbody>';

foreach ( $pt_objs as $pt => $pto ) {
echo '<tr data-pt="' . esc_attr( $pt ) . '">';
echo '<th scope="row" style="white-space:nowrap">' . esc_html( $pto->labels->name ) . ' <code>' . esc_html( $pt ) . '</code></th>';

foreach ( $tx_objs as $tx => $txo ) {
echo '<td style="text-align:center">';
if ( is_object_in_taxonomy( $pt, $tx ) ) {
$checked = ( ! empty( $required[ $pt ] ) && in_array( $tx, $required[ $pt ], true ) ) ? 'checked' : '';
echo '<label><input type="checkbox" name="sk_req[' . esc_attr( $pt ) . '][' . esc_attr( $tx ) . ']" value="1" ' . $checked . ' /></label>';
} else {
echo '&#8212;';
}
echo '</td>';
}

echo '</tr>';
}

echo '</tbody></table>';

submit_button( __( 'Save Changes', 'required-taxonomies' ) );

echo '<script>
(function(){
var q = document.getElementById("sk-matrix-search");
if(!q){return;}
q.addEventListener("input", function(){
var s = (q.value||"").toLowerCase();
document.querySelectorAll("#sk-matrix tbody tr").forEach(function(tr){
var rowText = tr.innerText.toLowerCase();
tr.style.display = rowText.indexOf(s) > -1 ? "" : "none";
});
});
})();
</script>';

echo '</form>';
echo '</div>';
}

function sk_tax_req_handle_settings_form() {
check_admin_referer( 'sk_tax_req_save', 'sk_tax_req_nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'You are not allowed to access this page.', 'required-taxonomies' ) );
}

$required = array();

$ptypes = get_post_types( array( 'public' => true, 'show_ui' => true ), 'names' );
$taxes  = get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'names' );

$ptypes = array_diff( $ptypes, array( 'attachment', 'nav_menu_item', 'revision', 'custom_css', 'customize_changeset', 'wp_block' ) );
$taxes  = array_diff( $taxes, array( 'post_format', 'nav_menu', 'link_category' ) );

if ( isset( $_POST['sk_req'] ) && is_array( $_POST['sk_req'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
foreach ( $ptypes as $pt ) {
if ( empty( $_POST['sk_req'][ $pt ] ) || ! is_array( $_POST['sk_req'][ $pt ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
continue;
}

foreach ( $taxes as $tax ) {
if ( isset( $_POST['sk_req'][ $pt ][ $tax ] ) && is_object_in_taxonomy( $pt, $tax ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
$required[ $pt ][] = sanitize_key( $tax );
}
}
}
}

update_option( SK_TAX_REQ_OPTION, $required );
wp_safe_redirect( add_query_arg( 'updated', '1', menu_page_url( 'sk-tax-required', false ) ) );
exit;
}

/* ----------------------------------------------------------------------------
 * Validation helpers
 * ------------------------------------------------------------------------- */

function sk_tax_req_get_required_for_pt( $post_type ) {
$matrix = get_option( SK_TAX_REQ_OPTION, array() );
return isset( $matrix[ $post_type ] ) ? (array) $matrix[ $post_type ] : array();
}

function sk_tax_req_post_has_terms( $post_id, $post_type, $tax ) {
if ( ! taxonomy_exists( $tax ) || ! is_object_in_taxonomy( $post_type, $tax ) ) {
return true;
}

$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
return ( is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) );
}

function sk_tax_req_is_publish_intent( $new_status, $orig_status = '' ) {
if ( in_array( $new_status, array( 'publish', 'future' ), true ) ) {
return true;
}

if ( 'pending' === $new_status ) {
return true;
}

if ( $orig_status && 'draft' === $orig_status && in_array( $new_status, array( 'publish', 'pending', 'future' ), true ) ) {
return true;
}

return false;
}

/* ----------------------------------------------------------------------------
 * Gutenberg / REST guard
 * ------------------------------------------------------------------------- */

add_action( 'rest_api_init', 'sk_tax_req_register_rest_guards' );

function sk_tax_req_register_rest_guards() {
$ptypes = get_post_types( array( 'public' => true ), 'names' );
$ptypes = array_diff( $ptypes, array( 'attachment', 'nav_menu_item', 'revision', 'custom_css', 'customize_changeset', 'wp_block' ) );

foreach ( $ptypes as $pt ) {
add_filter( "rest_pre_insert_{$pt}", function( $prepared, $request ) use ( $pt ) {
$req = sk_tax_req_get_required_for_pt( $pt );

if ( empty( $req ) ) {
return $prepared;
}

$status = isset( $prepared->post_status ) ? $prepared->post_status : $request->get_param( 'status' );

if ( ! $status || ! sk_tax_req_is_publish_intent( $status ) ) {
return $prepared;
}

$post_id = (int) $request->get_param( 'id' );
$missing = array();
$labels  = array();

foreach ( $req as $tax ) {
$txo = get_taxonomy( $tax );

if ( ! $txo ) {
continue;
}

$rest_field = $txo->rest_base ? $txo->rest_base : $txo->name;

if ( 'category' === $tax ) {
$rest_field = 'categories';
}

if ( 'post_tag' === $tax ) {
$rest_field = 'tags';
}

$val = $request->get_param( $rest_field );
$has = false;

if ( is_array( $val ) ) {
$has = count( array_filter( $val ) ) > 0;
} elseif ( null !== $val ) {
$has = ! empty( $val );
}

if ( ! $has && $post_id ) {
$has = sk_tax_req_post_has_terms( $post_id, $pt, $tax );
}

if ( ! $has ) {
$missing[] = $tax;
$labels[]  = $txo->labels->name ? $txo->labels->name : $tax;
}
}

if ( ! empty( $missing ) ) {
return new WP_Error(
'sk_required_tax_missing',
sprintf(
/* translators: %s: comma separated list of taxonomy names */
__( 'Publishing blocked: please assign at least one term for: %s.', 'required-taxonomies' ),
implode( ', ', $labels )
),
array(
'status'        => 400,
'missing'       => $labels,
'missing_slugs' => $missing,
)
);
}

return $prepared;
}, 10, 2 );
}
}

/* ----------------------------------------------------------------------------
 * Classic / Quick edit guard
 * ------------------------------------------------------------------------- */

add_filter( 'wp_insert_post_data', 'sk_tax_req_block_classic_publishing', 10, 2 );

function sk_tax_req_block_classic_publishing( $data, $postarr ) {
$post_type = isset( $postarr['post_type'] ) ? $postarr['post_type'] : $data['post_type'];
$req       = sk_tax_req_get_required_for_pt( $post_type );

if ( empty( $req ) ) {
return $data;
}

$orig_status = isset( $postarr['original_post_status'] ) ? $postarr['original_post_status'] : '';

if ( ! sk_tax_req_is_publish_intent( $data['post_status'], $orig_status ) ) {
return $data;
}

$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
$missing = array();

foreach ( $req as $tax ) {
if ( ! $post_id || ! sk_tax_req_post_has_terms( $post_id, $post_type, $tax ) ) {
$missing[] = $tax;
}
}

if ( ! empty( $missing ) ) {
$data['post_status'] = 'draft';
$GLOBALS['sk_tax_req_missing_for_redirect'] = implode( ',', array_map( 'sanitize_key', $missing ) );
}

return $data;
}

add_filter( 'redirect_post_location', 'sk_tax_req_inject_redirect_error', 99 );

function sk_tax_req_inject_redirect_error( $location ) {
if ( ! empty( $GLOBALS['sk_tax_req_missing_for_redirect'] ) ) {
$location = remove_query_arg( 'message', $location );
$location = add_query_arg( 'sk_tax_req_error', rawurlencode( $GLOBALS['sk_tax_req_missing_for_redirect'] ), $location );
unset( $GLOBALS['sk_tax_req_missing_for_redirect'] );
}

return $location;
}

/* ----------------------------------------------------------------------------
 * Notices + UI highlight (classic + block editor)
 * ------------------------------------------------------------------------- */

add_action( 'admin_notices', 'sk_tax_req_show_admin_notice' );

function sk_tax_req_show_admin_notice() {
if ( empty( $_GET['sk_tax_req_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
return;
}

$slugs  = array_map( 'sanitize_key', explode( ',', $_GET['sk_tax_req_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$labels = array();

foreach ( $slugs as $tx ) {
$txo = get_taxonomy( $tx );
$labels[] = $txo && ! empty( $txo->labels->name ) ? $txo->labels->name : $tx;
}

echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Publishing blocked.', 'required-taxonomies' ) . '</strong> ' . esc_html__( 'Please assign at least one term for:', 'required-taxonomies' ) . ' <em>' . esc_html( implode( ', ', $labels ) ) . '</em>.</p></div>';
echo '<style>';
foreach ( $slugs as $tx ) {
$tx = esc_html( $tx );
echo "#{$tx}div .inside, #tagsdiv-{$tx} .inside { border: 2px solid #dc3232 !important; box-shadow: 0 0 0 2px rgba(220,50,50,.12) inset; }";
}
echo '</style>';
}

add_action( 'enqueue_block_editor_assets', 'sk_tax_req_highlight_block_editor_panels' );

function sk_tax_req_highlight_block_editor_panels() {
wp_register_script(
'sk-tax-req-editor',
false,
array( 'wp-data', 'wp-edit-post', 'wp-dom-ready' ),
false,
true
);

wp_enqueue_script( 'sk-tax-req-editor' );

$js = <<<JS
wp.domReady(function(){
wp.data.subscribe(function(){
var isSaving = wp.data.select('core/editor').isSavingPost();
var isAutoSave = wp.data.select('core/editor').isAutosavingPost();

if ( isSaving || isAutoSave ) {
return;
}

var err = wp.data.select('core/editor').getPostSaveError();

if ( ! err ) {
clearHighlight();
return;
}

if ( err.code !== 'sk_required_tax_missing' || ! err.data ) {
clearHighlight();
return;
}

wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document');

document.querySelectorAll('.editor-post-taxonomies__hierarchical-terms-list, .components-form-token-field__input').forEach(function(el){
el.style.border = '2px solid #dc3232';
el.style.boxShadow = '0 0 0 2px rgba(220,50,50,.12) inset';
});
});

function clearHighlight(){
document.querySelectorAll('.editor-post-taxonomies__hierarchical-terms-list, .components-form-token-field__input').forEach(function(el){
el.style.border = '';
el.style.boxShadow = '';
});
}
});
JS;

wp_add_inline_script( 'sk-tax-req-editor', $js );
}
