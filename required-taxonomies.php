<?php
/**
 * Plugin Name: Required Taxonomies
 * Description: Enforce taxonomy selection per post type with a simple admin matrix and editor-side publish lock.
 * Version: 1.0.0
 * Author: OpenAI Assistant
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SK_TAX_REQ_OPTION', 'sk_tax_required_matrix' );

/* =========================
 * Instellingenpagina (matrix)
 * ========================= */
add_action( 'admin_menu', function () {
add_options_page(
'Tax verplicht',
'Tax verplicht',
'manage_options',
'sk-tax-verplicht',
'sk_tax_req_render_settings'
);
} );

function sk_tax_req_render_settings() {
if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
check_admin_referer( 'sk_tax_req_save', 'sk_tax_req_nonce' );
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen bevoegdheid.' );

$ptypes = get_post_types( array( 'public' => true, 'show_ui' => true ), 'names' );
$taxes  = get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'names' );

// Filter onzinnige items
$ptypes = array_diff( $ptypes, array( 'attachment','nav_menu_item','revision','custom_css','customize_changeset','wp_block' ) );
$taxes  = array_diff( $taxes,  array( 'post_format','nav_menu','link_category' ) );

$matrix = array();
if ( isset( $_POST['sk_req'] ) && is_array( $_POST['sk_req'] ) ) {
foreach ( $ptypes as $pt ) {
if ( empty( $_POST['sk_req'][ $pt ] ) ) continue;
foreach ( $taxes as $tx ) {
if ( isset( $_POST['sk_req'][ $pt ][ $tx ] ) && is_object_in_taxonomy( $pt, $tx ) ) {
$matrix[ $pt ][] = sanitize_key( $tx );
}
}
}
}

update_option( SK_TAX_REQ_OPTION, $matrix );

// Redirect/refresh zodat de matrix direct opnieuw rendert
$target_url = add_query_arg( 'updated', '1', menu_page_url( 'sk-tax-verplicht', false ) );

// 1) Normale redirect als headers nog niet verstuurd zijn
if ( ! headers_sent() ) {
wp_safe_redirect( $target_url );
exit;
}

// 2) Fallback voor WPCode/eval: forceer client-side refresh
$esc = esc_url( $target_url );
echo '<script>window.location.replace("'.$esc.'");</script>';
echo '<noscript><meta http-equiv="refresh" content="0;url='.$esc.'"></noscript>';
return; // stop verdere output
}

$matrix  = get_option( SK_TAX_REQ_OPTION, array() );
$pt_objs = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
$tx_objs = get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'objects' );

// Filter
unset( $pt_objs['attachment'], $pt_objs['nav_menu_item'], $pt_objs['revision'], $pt_objs['custom_css'], $pt_objs['customize_changeset'], $pt_objs['wp_block'] );
unset( $tx_objs['post_format'], $tx_objs['nav_menu'], $tx_objs['link_category'] );

echo '<div class="wrap"><h1>Verplichte taxonomieën per post type</h1>';
if ( isset($_GET['updated']) ) echo '<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen.</p></div>';

echo '<form method="post">';
wp_nonce_field( 'sk_tax_req_save', 'sk_tax_req_nonce' );
echo '<p>Selecteer per post type welke taxonomieën verplicht zijn (minimaal één term nodig om te kunnen publiceren/opslaan).</p>';
echo '<p><input id="sk-matrix-search" type="text" placeholder="Zoek post type of tax…" style="max-width:280px;padding:4px 8px"></p>';

// Matrix
echo '<table class="widefat striped" id="sk-matrix"><thead><tr><th style="min-width:220px">Post Type \\ Taxonomie</th>';
foreach ( $tx_objs as $tx => $txo ) {
echo '<th style="text-align:center;white-space:nowrap">' . esc_html( $txo->labels->name ) . '<br><code>'. esc_html($tx) .'</code></th>';
}
echo '</tr></thead><tbody>';

foreach ( $pt_objs as $pt => $pto ) {
echo '<tr><th scope="row" style="white-space:nowrap">'. esc_html($pto->labels->name) .' <code>'. esc_html($pt) .'</code></th>';
foreach ( $tx_objs as $tx => $txo ) {
echo '<td style="text-align:center">';
if ( is_object_in_taxonomy( $pt, $tx ) ) {
$checked = ( ! empty($matrix[$pt]) && in_array($tx, $matrix[$pt], true) ) ? 'checked' : '';
echo '<label><input type="checkbox" name="sk_req['. esc_attr($pt) .']['. esc_attr($tx) .']" value="1" '. $checked .' /></label>';
} else {
echo '&#8212;';
}
echo '</td>';
}
echo '</tr>';
}
echo '</tbody></table>';
submit_button( 'Instellingen opslaan' );

// simpele filter
echo '<script>
(function(){
var q=document.getElementById("sk-matrix-search"); if(!q) return;
q.addEventListener("input",function(){
var s=(q.value||"").toLowerCase();
document.querySelectorAll("#sk-matrix tbody tr").forEach(function(tr){
tr.style.display = tr.innerText.toLowerCase().indexOf(s)>-1 ? "" : "none";
});
});
})();
</script>';

echo '</form></div>';
}

/* =========================
 * Data + scripts voor editor
 * ========================= */
function sk_tax_req_get_required_for_pt( $post_type ) {
$matrix = get_option( SK_TAX_REQ_OPTION, array() );
return isset($matrix[$post_type]) ? (array) $matrix[$post_type] : array();
}

add_action( 'admin_enqueue_scripts', function( $hook ){
if ( ! in_array( $hook, array('post-new.php','post.php'), true ) ) return;

$screen = get_current_screen();
if ( empty( $screen->post_type ) ) return;

$required_tax = sk_tax_req_get_required_for_pt( $screen->post_type );
if ( empty( $required_tax ) ) return;

// Bouw mapping: tax_slug => ['label'=>..., 'rest_base'=>..., 'hierarchical'=>true/false]
$map = array();
foreach ( $required_tax as $tx ) {
$txo = get_taxonomy( $tx );
if ( ! $txo ) continue;
$rest = $txo->rest_base ? $txo->rest_base : $txo->name;
if ( 'category' === $tx ) $rest = 'categories';
if ( 'post_tag' === $tx ) $rest = 'tags';
$map[ $tx ] = array(
'label'        => $txo->labels->name ?: $tx,
'rest_base'    => $rest,
'hierarchical' => (bool) $txo->hierarchical,
);
}

/* -------- Classic editor -------- */
wp_register_script( 'sk-tax-req-classic', false, array('jquery'), false, true );
wp_enqueue_script( 'sk-tax-req-classic' );

$cfg_json = wp_json_encode( $map );
$classic_js = <<<'JS'
(function($){
var cfg = __CFG__;
// Meldingsbalk
function ensureNotice(msg){
var id="sk-tax-req-notice";
var $n = $("#"+id);
if(!$n.length){
$n = $('<div id="'+id+'" class="notice notice-error is-dismissible" style="margin:10px 0;"><p></p></div>');
$(".wrap h1").first().after($n);
}
$n.find("p").text(msg);
}
function clearNotice(){ $("#sk-tax-req-notice").remove(); }

function hasTermsClassic(slug, hierarchical){
if(hierarchical){
// Checkboxes in #taxonomy-{slug} of #{slug}div
var sel1 = '#taxonomy-' + slug + ' input[type=checkbox][name^="tax_input[' + slug + ']"]';
var sel2 = '#' + slug + 'div input[type=checkbox][name^="tax_input[' + slug + ']"]';
if( $(sel1).filter(':checked').length > 0 || $(sel2).filter(':checked').length > 0 ){
return true;
}
// Ook nested UL/LIs fallback
var wrapSel1 = '#taxonomy-' + slug + ' .categorychecklist input[type=checkbox]:checked';
var wrapSel2 = '#' + slug + 'div .categorychecklist input[type=checkbox]:checked';
if( $(wrapSel1).length > 0 || $(wrapSel2).length > 0 ){
return true;
}
} else {
// Tags UI: hidden input tax_input[slug] of tokenfield items
var inputSel = 'input[name="tax_input[' + slug + ']"]';
if( $(inputSel).length && $.trim($(inputSel).val()).length ){
return true;
}
if( $('#tagsdiv-' + slug + ' .tagchecklist > span').length > 0 ){
return true;
}
}
return false;
}

function checkAll(){
var missing=[];
$.each(cfg, function(slug,meta){
if(!hasTermsClassic(slug, meta.hierarchical)){ missing.push(meta.label); }
});
var $btn = $("#publish, #save-post");
if(missing.length){
$btn.prop("disabled", true).addClass("button-disabled");
ensureNotice("Publiceren/opslaan geblokkeerd: voeg minimaal één term toe voor: " + missing.join(", ") + ".");
}else{
$btn.prop("disabled", false).removeClass("button-disabled");
clearNotice();
}
}

$(document).on("change input click", function(){ setTimeout(checkAll, 60); });
$(function(){ setTimeout(checkAll, 250); });
})(jQuery);
JS;
$classic_js = str_replace( '__CFG__', $cfg_json, $classic_js );
wp_add_inline_script( 'sk-tax-req-classic', $classic_js );

/* -------- Gutenberg editor -------- */
wp_register_script(
'sk-tax-req-editor',
false,
array( 'wp-data','wp-edit-post','wp-dom-ready','wp-notices' ),
false,
true
);
wp_enqueue_script( 'sk-tax-req-editor' );

$gutenberg_js = <<<'JS'
(function(){
var cfg = __CFG__;
var LOCK_KEY = "sk-tax-required";

function getTermsFor(restBase){
var val = wp.data.select("core/editor").getEditedPostAttribute(restBase);
if(typeof val === "number"){ return val ? [val] : []; }
if(Array.isArray(val)) return val.filter(Boolean);
if(typeof val === "string") return val.trim() ? val.split(",") : [];
return [];
}

function evaluate(){
var missing = [];
Object.keys(cfg).forEach(function(slug){
var meta  = cfg[slug];
var terms = getTermsFor(meta.rest_base);
if(!terms || terms.length === 0){ missing.push(meta.label); }
});
if(missing.length){
wp.data.dispatch("core/editor").lockPostSaving(LOCK_KEY);
wp.data.dispatch("core/notices").createNotice(
"error",
"Publiceren/opslaan geblokkeerd: voeg minimaal één term toe voor: " + missing.join(", ") + ".",
{ id: "sk-tax-req", isDismissible: true }
);
}else{
wp.data.dispatch("core/editor").unlockPostSaving(LOCK_KEY);
wp.data.dispatch("core/notices").removeNotice("sk-tax-req");
}
}

wp.domReady(function(){
wp.data.subscribe(function(){ setTimeout(evaluate, 0); });
setTimeout(evaluate, 250);
});
})();
JS;
$gutenberg_js = str_replace( '__CFG__', $cfg_json, $gutenberg_js );
wp_add_inline_script( 'sk-tax-req-editor', $gutenberg_js );
});