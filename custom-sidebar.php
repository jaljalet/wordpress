<?php
function sidebarname_sidebar() {
    register_sidebar(
        array (
            'name' => __( 'Title', 'yourdomain' ),
            'id' => 'kampanya',
            'description' => __( 'Custom Sidebar', 'yourdomain' ),
            'before_widget' => '<div class="widget-content">',
            'after_widget' => "</div>",
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        )
    );
}
add_action( 'widgets_init', 'sidebarname_sidebar' );
