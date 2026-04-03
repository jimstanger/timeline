<?php
/**
 * Plugin Name: Modern Timeline
 * Description: A custom timeline plugin with multiple modern infographic designs.
 * Version: 1.2.8
 * Author: Jim Stanger
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Modern_Timeline {

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type_and_tax' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) ); 
        add_action( 'add_meta_boxes', array( $this, 'add_timeline_meta_boxes' ) ); 
        add_action( 'save_post', array( $this, 'save_timeline_meta_boxes' ) ); 
        add_shortcode( 'timeline', array( $this, 'render_timeline_shortcode' ) );
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    public function register_post_type_and_tax() {
        register_taxonomy( 'timeline_topic', 'timeline_item', array(
            'labels'       => array( 'name' => 'Timeline Topics', 'singular_name' => 'Topic' ),
            'hierarchical' => true,
            'show_admin_column' => true,
        ));

        register_post_type( 'timeline_item', array(
            'labels'      => array( 'name' => 'Timeline Items', 'singular_name' => 'Timeline Item' ),
            'public'      => true,
            'supports'    => array( 'title', 'editor', 'thumbnail' ),
            'menu_icon'   => 'dashicons-chart-line',
            'has_archive' => false,
        ));
    }

    public function enqueue_admin_scripts( $hook ) {
        global $post_type;
        if ( ( 'post.php' == $hook || 'post-new.php' == $hook ) && 'timeline_item' == $post_type ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_add_inline_script( 'wp-color-picker', 'jQuery(document).ready(function($){ $(".mt-color-picker").wpColorPicker(); });' );
        }
    }

    public function add_timeline_meta_boxes() {
        add_meta_box( 'timeline_settings_box', 'Timeline Settings', array( $this, 'render_meta_boxes' ), 'timeline_item', 'side', 'high' );
    }

    public function render_meta_boxes( $post ) {
        wp_nonce_field( 'timeline_meta_nonce_action', 'timeline_meta_nonce' );
        
        $date_value = get_post_meta( $post->ID, '_timeline_date', true );
        $color_value = get_post_meta( $post->ID, '_timeline_color', true );
        if ( empty( $color_value ) ) $color_value = '#00B8D4';

        echo '<p><label for="timeline_date"><strong>Event Date (Used for sorting):</strong></label><br>';
        echo '<input type="date" id="timeline_date" name="timeline_date" value="' . esc_attr( $date_value ) . '" style="width:100%; margin-top:5px;" /></p>';

        echo '<p><label for="timeline_color"><strong>Primary Color:</strong></label><br>';
        echo '<input type="text" id="timeline_color" name="timeline_color" value="' . esc_attr( $color_value ) . '" class="mt-color-picker" /></p>';
    }

    public function save_timeline_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['timeline_meta_nonce'] ) || ! wp_verify_nonce( $_POST['timeline_meta_nonce'], 'timeline_meta_nonce_action' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['timeline_date'] ) ) {
            update_post_meta( $post_id, '_timeline_date', sanitize_text_field( $_POST['timeline_date'] ) );
        }
        
        if ( isset( $_POST['timeline_color'] ) ) {
            update_post_meta( $post_id, '_timeline_color', sanitize_hex_color( $_POST['timeline_color'] ) );
        }
    }

    public function register_settings_page() {
        add_options_page( 'Timeline Settings', 'Timeline', 'manage_options', 'modern-timeline', array( $this, 'settings_page_html' ) );
    }

    public function register_settings() {
        register_setting( 'modern_timeline_options', 'modern_timeline_design' );
    }

    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1>Timeline Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'modern_timeline_options' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Default Timeline Design</th>
                        <td>
                            <select name="modern_timeline_design">
                                <option value="alternating-vertical" <?php selected( get_option('modern_timeline_design'), 'alternating-vertical' ); ?>>Alternating Vertical</option>
                                <option value="alternating-vertical-small" <?php selected( get_option('modern_timeline_design'), 'alternating-vertical-small' ); ?>>Alternating Vertical Small</option>
                                <option value="horizontal-flag-cards" <?php selected( get_option('modern_timeline_design'), 'horizontal-flag-cards' ); ?>>Horizontal Flag Cards</option>
                            </select>
                            <p class="description">This design will be used globally unless you override it in a specific shortcode.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr style="margin-top: 30px; margin-bottom: 30px;">

            <h2>How to Use the Shortcode</h2>
            <p>To display your timelines on any post or page, use the <code>[timeline]</code> shortcode. You must specify which <strong>Topic</strong> to display by using its slug.</p>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><strong>Basic Usage:</strong></th>
                    <td>
                        <code>[timeline topic="company-history"]</code>
                        <p class="description">Replace <code>company-history</code> with the actual slug of the Timeline Topic you want to show.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><strong>Override Design:</strong></th>
                    <td>
                        <code>[timeline topic="company-history" design="alternating-vertical-small"]</code><br><br>
                        <code>[timeline topic="company-history" design="horizontal-flag-cards"]</code><br><br>
                        <code>[timeline topic="company-history" design="alternating-vertical"]</code>
                        <p class="description">Add the <code>design</code> parameter to force a specific layout for that single page, ignoring the global default setting above.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function enqueue_styles() {
        wp_enqueue_style( 'modern-timeline-css', plugin_dir_url( __FILE__ ) . 'timeline.css', array(), '3.1.0' );
    }

    public function render_timeline_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'topic' => '', 'design' => '' ), $atts, 'timeline' );
        $args = array(
            'post_type'      => 'timeline_item',
            'posts_per_page' => -1,
            'meta_key'       => '_timeline_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        );

        if ( ! empty( $atts['topic'] ) ) {
            $args['tax_query'] = array( array( 'taxonomy' => 'timeline_topic', 'field' => 'slug', 'terms' => $atts['topic'] ) );
        }

        $query = new WP_Query( $args );
        if ( ! $query->have_posts() ) return '<p>No timeline events found.</p>';

        $design = ! empty( $atts['design'] ) ? $atts['design'] : get_option( 'modern_timeline_design', 'alternating-vertical' );
        
        ob_start();

        if ( $design === 'alternating-vertical-small' ) {
            echo '<div class="mt-timeline-small-container">';
            $count = 0;
            while ( $query->have_posts() ) {
                $query->the_post();
                $date_meta = get_post_meta( get_the_ID(), '_timeline_date', true );
                $year = $date_meta ? date( 'Y', strtotime( $date_meta ) ) : '';
                $month_day = $date_meta ? date_i18n( 'F j', strtotime( $date_meta ) ) : '';
                $color = get_post_meta( get_the_ID(), '_timeline_color', true ) ?: '#00B8D4';
                $side = ( $count % 2 == 0 ) ? 'mt-left' : 'mt-right';
                ?>
                <div class="mt-s-item <?php echo $side; ?>" style="--mt-item-color: <?php echo esc_attr($color); ?>;">
                    <div class="mt-s-card">
                        <div class="mt-s-year"><?php echo esc_html($year); ?></div>
                        <div class="mt-s-content">
                            <?php if ( $month_day ) : ?>
                                <div class="mt-s-date" style="color: var(--mt-item-color);"><?php echo esc_html($month_day); ?></div>
                            <?php endif; ?>
                            <h3><?php the_title(); ?></h3>
                            <div class="mt-s-desc"><?php the_content(); ?></div>
                        </div>
                    </div>
                    <div class="mt-s-node"></div>
                </div>
                <?php
                $count++;
            }
            echo '</div>';
        } elseif ( $design === 'horizontal-flag-cards' ) {
            echo '<div class="mt-timeline-horizontal-container"><div class="mt-h-items-wrapper">';
            while ( $query->have_posts() ) {
                $query->the_post();
                $date_meta = get_post_meta( get_the_ID(), '_timeline_date', true );
                $display_year = $date_meta ? date( 'Y', strtotime( $date_meta ) ) : '';
                $display_full_date = $date_meta ? date_i18n( get_option( 'date_format' ), strtotime( $date_meta ) ) : '';
                $bg_color = get_post_meta( get_the_ID(), '_timeline_color', true ) ?: '#00B8D4';
                ?>
                <div class="mt-h-item" style="--mt-item-color: <?php echo esc_attr($bg_color); ?>;">
                    <div class="mt-h-card">
                        <div class="mt-h-icon"><?php has_post_thumbnail() ? the_post_thumbnail('thumbnail') : print('<span style="color:white; font-size: 24px;">★</span>'); ?></div>
                        <div class="mt-h-subtitle" style="color: var(--mt-item-color);"><?php echo esc_html( $display_full_date ); ?></div>
                        <h3><?php the_title(); ?></h3>
                        <div class="mt-h-desc"><?php the_content(); ?></div>
                    </div>
                    <div class="mt-h-node"></div>
                    <div class="mt-h-year"><?php echo esc_html( $display_year ); ?></div>
                </div>
                <?php
            }
            echo '</div></div>';
        } else {
            echo '<div class="mt-timeline-container">';
            $count = 0;
            while ( $query->have_posts() ) {
                $query->the_post();
                $date_meta = get_post_meta( get_the_ID(), '_timeline_date', true );
                $display_year = $date_meta ? date( 'Y', strtotime( $date_meta ) ) : '';
                $bg_color = get_post_meta( get_the_ID(), '_timeline_color', true ) ?: '#00B8D4';
                $side_class = ( $count % 2 == 0 ) ? 'mt-left' : 'mt-right';
                ?>
                <div class="mt-timeline-item <?php echo $side_class; ?>" style="--mt-item-color: <?php echo esc_attr($bg_color); ?>;">
                    <div class="mt-content">
                        <span class="mt-year-badge"><?php echo esc_html( $display_year ); ?></span>
                        <h3><?php the_title(); ?></h3>
                        <div class="mt-desc"><?php the_content(); ?></div>
                        <?php if ( has_post_thumbnail() ) : ?><div class="mt-image"><?php the_post_thumbnail('medium'); ?></div><?php endif; ?>
                    </div>
                </div>
                <?php
                $count++;
            }
            echo '</div>';
        }

        wp_reset_postdata();
        return ob_get_clean();
    }
}
new Modern_Timeline();
