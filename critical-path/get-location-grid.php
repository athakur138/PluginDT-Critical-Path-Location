<?php
require_once ('../../../../wp-load.php');
global $wpdb;
$find = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$res  = $wpdb->get_results(
            $wpdb->prepare(
        "SELECT
                    grid_id, name
                FROM
                    $wpdb->dt_location_grid
                WHERE
                    name LIKE %s", '%' . $find . '%'
                )
            );

echo json_encode($res);die;

