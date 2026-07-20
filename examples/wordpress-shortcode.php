<?php

declare(strict_types=1);

/**
 * Example WordPress integration. This file is documentation-oriented and is not loaded by the package.
 */
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'livewire-bridge',
        home_url('/vendor/livewire-bridge/livewire-bridge.css'),
        [],
        '0.1.0'
    );

    wp_enqueue_script(
        'livewire-bridge',
        home_url('/vendor/livewire-bridge/livewire-bridge.js'),
        [],
        '0.1.0',
        true
    );
});

add_shortcode('reservation_form', 'trust_medical_render_reservation_form_shortcode');

function trust_medical_render_reservation_form_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'placement' => 'price-page',
    ], $atts, 'reservation_form');

    $allowedPlacements = ['price-page', 'menu-page', 'campaign-page'];
    $placement = sanitize_key((string) $atts['placement']);

    if (! in_array($placement, $allowedPlacements, true)) {
        return '';
    }

    $params = [
        'placement' => $placement,
    ];

    return sprintf(
        '<livewire-bridge data-component="reservation" data-params="%s" data-clarity-mask="true"></livewire-bridge>',
        esc_attr((string) wp_json_encode($params))
    );
}

add_action('wp_footer', static function (): void {
    ?>
    <script>
    document.addEventListener('livewire-bridge:started', function (event) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'livewire_bridge_started',
            component_count: event.detail.componentCount
        });
    });

    document.addEventListener('reservation-form:submitted', function () {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'reservation_form_submitted'
        });
    });
    </script>
    <?php
});
