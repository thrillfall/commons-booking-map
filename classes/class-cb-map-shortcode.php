<?php

class CB_Map_Shortcode {

  /**
  * the shortcode handler - load all the needed assets and render the map container
  **/
  public static function execute($atts) {

    $a = shortcode_atts( array(
  		'id' => 0
  	), $atts );

    if((int) $a['id']) {
      $post = get_post($a['id']);

      if($post && $post->post_type == 'cb_map') {
        $cb_map_id = $post->ID;

        $map_type = CB_Map_Admin::get_option($cb_map_id, 'map_type');

        if($post->post_status == 'publish') {
          if($map_type == 1 || $map_type == 2) {
            //leaflet
            wp_enqueue_style('cb_map_leaflet_css', CB_MAP_ASSETS_URL . 'leaflet/leaflet.css');
            wp_enqueue_script( 'cb_map_leaflet_js', CB_MAP_ASSETS_URL . 'leaflet/leaflet.js' );

            //leaflet markercluster plugin
            wp_enqueue_style('cb_map_leaflet_markercluster_css', CB_MAP_ASSETS_URL . 'leaflet-markercluster/MarkerCluster.css');
            wp_enqueue_style('cb_map_leaflet_markercluster_default_css', CB_MAP_ASSETS_URL . 'leaflet-markercluster/MarkerCluster.Default.css');
            wp_enqueue_script( 'cb_map_leaflet_markercluster_js', CB_MAP_ASSETS_URL . 'leaflet-markercluster/leaflet.markercluster.js' );

            //leaflet messagebox plugin
            wp_enqueue_style('cb_map_leaflet_messagebox_css', CB_MAP_ASSETS_URL . 'leaflet-messagebox/leaflet-messagebox.css');
            wp_enqueue_script('cb_map_leaflet_messagebox_js', CB_MAP_ASSETS_URL . 'leaflet-messagebox/leaflet-messagebox.js');

            //leaflet spin & dependencies
            wp_enqueue_style( 'cb_map_spin_css', CB_MAP_ASSETS_URL . 'spin-js/spin.css' );
            wp_enqueue_script( 'cb_map_spin_js', CB_MAP_ASSETS_URL . 'spin-js/spin.min.js' );
            wp_enqueue_script( 'cb_map_leaflet_spin_js', CB_MAP_ASSETS_URL . 'leaflet-spin/leaflet.spin.min.js' );

            //dashicons
            wp_enqueue_style('dashicons');

            //overscroll
            wp_enqueue_script( 'cb_map_slider_js', CB_MAP_ASSETS_URL . 'overscroll/jquery.overscroll.js' );

            //cb map shortcode
            wp_enqueue_style('cb_map_shortcode_css', CB_MAP_ASSETS_URL . 'css/cb-map-shortcode.css');
            wp_register_script( 'cb_map_shortcode_js', CB_MAP_ASSETS_URL . 'js/cb-map-shortcode.js');

            wp_add_inline_script( 'cb_map_shortcode_js',
              "jQuery(document).ready(function ($) {
                var cb_map = new CB_Map();
                cb_map.settings = " . json_encode(self::get_settings($cb_map_id)) . ";
                cb_map.translation = " . json_encode(self::get_translation($cb_map_id)) . ";
                console.log('cb_map.settings: ', cb_map.settings);
                cb_map.init_filters($);
                cb_map.init_map();
            });");

            wp_enqueue_script( 'cb_map_shortcode_js' );

            $map_height = CB_Map_Admin::get_option($cb_map_id, 'map_height');
            return '<div id="cb-map-' . $cb_map_id . '" style="width: 100%; height: ' . $map_height . 'px;"></div>';
          }
          else {
            return '<div>' . cb_map\__( 'NO_VALID_MAP_TYPE', 'commons-booking-map', 'no valid map type') . '</div>';
          }
        }
        else {
          return '<div>' . cb_map\__( 'NO_VALID_POST_STATUS', 'commons-booking-map', 'map is not published') . '</div>';
        }
      }
      else {
        return '<div>' . cb_map\__( 'NO_VALID_MAP_ID', 'commons-booking-map', 'no valid map id provided') . '</div>';
      }

    }
    else {
      return '<div>' . cb_map\__( 'NO_VALID_MAP_ID', 'commons-booking-map', 'no valid map id provided') . '</div>';
    }

  }

  /**
  * get the settings for the frontend of the map with given id
  **/
  public static function get_settings($cb_map_id) {
    $commons_booking_settings = get_option('commons-booking-settings-bookings');

    $date_min = new DateTime();
    $date_min = $date_min->format('Y-m-d');
    $max_days_in_future = $commons_booking_settings['commons-booking_bookingsettings_daystoshow'] - 1;
    $date_max = new DateTime($date_min . ' +' . $max_days_in_future . ' days');
    $date_max = $date_max->format('Y-m-d');

    $maxdays = $commons_booking_settings['commons-booking_bookingsettings_maxdays'];

    $settings = [
      'data_url' => get_site_url(null, '', null) . '/wp-admin/admin-ajax.php',
      'nonce' => wp_create_nonce('cb_map_locations'),
      'marker_icon' => null,
      'filter_cb_item_categories' => [],
      'filter_availability' => [
        'date_min' => $date_min,
        'date_max' => $date_max,
        'day_count_max' => $maxdays
      ],
      'cb_map_id' => $cb_map_id,
      'locale' => str_replace('_', '-', get_locale())
    ];

    $options = CB_Map_Admin::get_options($cb_map_id, true);

    $pass_through = [
      'base_map',
      'show_scale', 'zoom_min', 'zoom_max', 'zoom_start', 'lat_start', 'lon_start',
      'marker_map_bounds_initial', 'marker_map_bounds_filter', 'max_cluster_radius',
      'show_location_contact', 'show_location_opening_hours', 'show_item_availability',
      'show_item_availability_filter', 'label_item_availability_filter', 'label_item_category_filter'
    ];

    $icon_size = [$options['marker_icon_width'], $options['marker_icon_height']];
    $icon_anchor = [$options['marker_icon_anchor_x'], $options['marker_icon_anchor_y']];

    foreach ($options as $key => $value) {
      if(in_array($key, $pass_through)) {
        $settings[$key] = $value;
      }
      else if($key == 'custom_marker_media_id') {
        if($value != null) {
          $settings['marker_icon'] = [
            'iconUrl'       => wp_get_attachment_url($options['custom_marker_media_id']),
            //'shadowUrl'     => 'leaf-shadow.png',

            'iconSize'      => $icon_size, //[27, 35], // size of the icon
            //'shadowSize'    => [50, 64], // size of the shadow
            'iconAnchor'    => $icon_anchor, //[13.5, 0], // point of the icon which will correspond to marker's location
            //'shadowAnchor'  => [4, 62],  // the same for the shadow
            //'popupAnchor'   => [-3, -76] // point from which the popup should open relative to the iconAnchor
          ];
        }
      }
      else if($key == 'custom_marker_cluster_media_id') {
        if($value != null) {
          $settings['marker_cluster_icon'] = [
            'url'       => wp_get_attachment_url($options['custom_marker_cluster_media_id']),
            'size'      => [
              'width' => $options['marker_cluster_icon_width'],
              'height' => $options['marker_cluster_icon_height']
            ]
          ];
        }
      }
      //categories are only meant to be shown on local maps
      else if($key == 'cb_items_available_categories' && $options['map_type'] == 1) {
        $settings['filter_cb_item_categories'] = [];
        $current_group_id = null;
        foreach ($options['cb_items_available_categories'] as $key => $content) {
          if(substr($key, 0, 1) == 'g') {
            $current_group_id = $key;
            $settings['filter_cb_item_categories'][$key] = [
              'name' => $content,
              'elements' => []
            ];
          }
          else {
            $settings['filter_cb_item_categories'][$current_group_id]['elements'][] = [
              'cat_id' => $key,
              'markup' => $content
            ];
          }
        }
      }

    }

    return $settings;
  }

  /**
  * get the translations for the frontend
  **/
  public static function get_translation($cb_map_id) {
    $label_location_opening_hours = CB_Map_Admin::get_option($cb_map_id, 'label_location_opening_hours');
    $label_location_contact = CB_Map_Admin::get_option($cb_map_id, 'label_location_contact');
    $custom_no_locations_message = CB_Map_Admin::get_option($cb_map_id, 'custom_no_locations_message');
    $label_item_availability_filter = CB_Map_Admin::get_option($cb_map_id, 'label_item_availability_filter');
    $label_item_category_filter = CB_Map_Admin::get_option($cb_map_id, 'label_item_category_filter');

    $translation = [
      'OPENING_HOURS' => strlen($label_location_opening_hours) > 0 ? $label_location_opening_hours : cb_map\__('OPENING_HOURS', 'commons-booking-map', 'opening hours'),
      'CONTACT' => strlen($label_location_contact) > 0 ? $label_location_contact : cb_map\__('CONTACT', 'commons-booking-map', 'contact'),
      'FROM' => cb_map\__( 'FROM', 'commons-booking-map', 'from'),
      'UNTIL' => cb_map\__( 'UNTIL', 'commons-booking-map', 'until'),
      'AT_LEAST' => cb_map\__( 'AT_LEAST', 'commons-booking-map', 'for at least'),
      'DAYS' => cb_map\__( 'DAYS', 'commons-booking-map', 'day(s)'),
      'NO_LOCATIONS_MESSAGE' => strlen($custom_no_locations_message) > 0 ? $custom_no_locations_message : cb_map\__( 'NO_LOCATIONS_MESSAGE', 'commons-booking-map', 'Sorry, no locations found.'),
      'FILTER' => cb_map\__( 'FILTER', 'commons-booking-map', 'filter'),
      'AVAILABILITY' => strlen($label_item_availability_filter) > 0 ? $label_item_availability_filter : cb_map\__( 'AVAILABILITY', 'commons-booking-map', 'availability'),
      'CATEGORIES' => strlen($label_item_category_filter) > 0 ? $label_item_category_filter : cb_map\__( 'CATEGORIES', 'commons-booking-map', 'categories'),
    ];

    return $translation;
  }

  /**
  * the ajax request handler for locations
  **/
  public static function get_locations() {

    //handle export
    if(isset($_POST['code'])) {

      //find map with corresponding code
      $args = [
        'post_type' => 'cb_map'
      ];
      $cb_maps = get_posts($args);

      foreach ($cb_maps as $cb_map) {
        $options = get_post_meta( $cb_map->ID, 'cb_map_options', true );

        //var_dump($options);

        if($options['map_type'] == 3 && $options['export_code'] == $_POST['code']) {
          $cb_map_id = $cb_map->ID;
          $map_type = 3;
          $post = $cb_map;
          break;
        }
      }

      if(!isset($cb_map_id)) {
        wp_send_json_error([ 'error' => 1 ], 404);
        return wp_die();
      }
    }
    //handle local/import map
    else if(isset($_POST['cb_map_id'])) {
      check_ajax_referer( 'cb_map_locations', 'nonce' );

      $post = get_post((int) $_POST['cb_map_id']);

      if($post && $post->post_type == 'cb_map') {
        $cb_map_id = $post->ID;

        //prepare response payload
        $map_type = CB_Map_Admin::get_option($cb_map_id, 'map_type');
      }
      else {
        wp_send_json_error( [ 'error' => 2 ], 400);
        return wp_die();
      }
    }
    else {
      wp_send_json_error( [ 'error' => 3 ], 400);
      return wp_die();
    }

    $preset_categories = CB_Map_Admin::get_option($cb_map_id, 'cb_items_preset_categories');

    if($post->post_status == 'publish') {
      require_once( CB_MAP_PATH . 'classes/class-cb-map.php' );
      require_once( CB_MAP_PATH . 'classes/class-cb-map-filter.php' );

      //local - get the locations and apply provided filters
      if($map_type == 1) {
        $available_user_categories = array_keys(CB_Map_Admin::get_option($cb_map_id, 'cb_items_available_categories'));
        $user_categories = [];
        $location_filters = [];
        $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : [];

        if(isset($filters['cb_item_categories']) && is_array($filters['cb_item_categories'])) {
          foreach($filters['cb_item_categories'] as $filter) {
            if(in_array((int) $filter, $available_user_categories)) {
              $user_categories[] = (int) $filter;
            }
          }
        }

        $location_filters['timeframes_and_categories'] = [
          'preset_categories' => $preset_categories,
          'user_categories' => $user_categories
        ];

        $locations = CB_Map::get_locations($cb_map_id);
        $locations = CB_Map_Filter::filter_locations($locations, $cb_map_id, $location_filters);

        $settings = self::get_settings($cb_map_id);
        $default_date_start = $settings['filter_availability']['date_min'];
        $default_date_end = $settings['filter_availability']['date_max'];

        //if availability filter is enabled & any related input is set: validate availability filter input
        if(CB_Map_Admin::get_option($cb_map_id, 'show_item_availability_filter') && (isset($filters['date_start']) || isset($filters['date_end'] ) || isset($filters['day_count']))) {

          $date_start = isset($filters['date_start']) && strlen($filters['date_start']) > 0 && new DateTime($filters['date_start']) && new DateTime($filters['date_start']) >= new DAteTime($default_date_start) ? $filters['date_start'] : $default_date_start;
          $date_end = isset($filters['date_end']) && strlen($filters['date_end']) > 0 && new DateTime($filters['date_start']) && new DateTime($filters['date_end']) <= new DateTime($default_date_end) ? $filters['date_end']: $default_date_end;

          //ensure: date_start < date_end
          if(new DateTime($date_start) > new DateTime($date_end)) {
            $tmp_date = $date_start;
            $date_start = $date_end;
            $date_end = $tmp_date;
          }

          $locations = CB_Map_Item_Availability::create_items_availabilities($locations, $date_start, $date_end);

          $location_filter['item_availability'] = [
            'day_count' => isset($filters['day_count']) && (int) $filters['day_count'] > 0 && (int) $filters['day_count'] <= $settings['filter_availability']['day_count_max'] ? (int) $filters['day_count'] : 1
          ];

          $locations = CB_Map_Filter::filter_locations($locations, $cb_map_id, $location_filter);
        }
        else {
          //create availabilities
          $show_item_availability = CB_Map_Admin::get_option($cb_map_id, 'show_item_availability');
          if($show_item_availability) {
            $locations = CB_Map_Item_Availability::create_items_availabilities($locations, $default_date_start, $default_date_end);
          }

        }
        $locations = CB_Map_Item_Availability::availability_to_indexed_array($locations);
        $locations = array_values($locations); //locations to indexed array
        $locations = CB_Map::cleanup_location_data($locations, '<br>', $map_type);

      }

      //import - get locations that are imported and stored in db
      if($map_type == 2) {
        $map_imports = get_post_meta( $cb_map_id, 'cb_map_imports', true );

        $locations = [];

        if(is_array($map_imports)) {
          foreach ($map_imports as $import_locations_string) {
            $import_locations = json_decode(base64_decode($import_locations_string), true);
            if(is_array($import_locations)) {
              $locations = array_merge($locations, $import_locations);
            }
          }
        }
      }

      //export - get the locations that are supposed to be provided for external usage
      if($map_type == 3) {
        $preset_categories = CB_Map_Admin::get_option($cb_map_id, 'cb_items_preset_categories');
        $locations = CB_Map::get_locations($cb_map_id);
        $filters = [
          'timeframes_and_categories' => [
            'preset_categories' => $preset_categories
          ]
        ];
        $locations = CB_Map_Filter::filter_locations($locations, $cb_map_id, $filters);
        $locations = array_values($locations); //locations to indexed array
        $locations = CB_Map::cleanup_location_data($locations, '<br>', $map_type);
      }

      echo json_encode($locations, JSON_UNESCAPED_UNICODE);

      return wp_die();

    }
    else {
      wp_send_json_error( [ 'error' => 4 ], 403);
      return wp_die();
    }
  }
}
?>
