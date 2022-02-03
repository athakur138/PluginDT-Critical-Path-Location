<?php
/**
 * Counts Misc Contacts numbers
 *
 * @package Disciple.Tools
 * @version 0.1.0
 */
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Counter_Contacts
 */
class Disciple_Tools_Query extends Disciple_Tools_Counter_Base
{

    public static $total;
    public static $generations;

    /**
     * Constructor function.
     *
     * @access public
     * @since  0.1.0
     */
    public function __construct()
    {
        parent::__construct();
    } // End __construct()

    /**
     * Returns count of contacts for different statuses
     * Primary 'countable'
     *
     * @param string $status
     * @param int $start
     * @param null $end
     *
     * @return int
     */
    public static function get_contacts_count(string $status = '', $start = 0, $end = null)
    {
        global $wpdb;
        $status = strtolower($status);
        if (!$end || $end === PHP_INT_MAX) {
            $end = strtotime("2100-01-01");
        }

        switch ($status) {

            case 'new_contacts':
                $res = $wpdb->get_var($wpdb->prepare("
                SELECT count(ID) as count
                FROM $wpdb->posts
                WHERE post_type = 'contacts'
                  AND post_status = 'publish'
                  AND post_date >= %s
                  AND post_date < %s
                  AND ID NOT IN (
                    SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND  meta_value = 'user'
                    GROUP BY post_id
                )", dt_format_date($start, 'Y-m-d'), dt_format_date($end, 'Y-m-d')));
                return $res;
                break;

            case 'contacts_attempted':

                return 0;
                break;

            case 'contacts_established':

                return 0;
                break;

            case 'first_meetings':
                $res = $wpdb->get_var($wpdb->prepare("
                SELECT count(DISTINCT(a.ID)) as count
                FROM $wpdb->posts as a
                JOIN (
                    SELECT object_id, MIN( c.hist_time ) min_time
                        FROM $wpdb->dt_activity_log c
                        WHERE c.object_type = 'contacts'
                        AND c.meta_key = 'seeker_path'
                        AND ( c.meta_value = 'met' OR c.meta_value = 'ongoing' OR c.meta_value = 'coaching' )
                        GROUP BY c.object_id
                ) b
                ON a.ID = b.object_id
                WHERE a.post_status = 'publish'
                  AND b.min_time  BETWEEN %s and %s
                  AND a.post_type = 'contacts'
                  AND a.ID NOT IN (
                    SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND  meta_value = 'user'
                    GROUP BY post_id
                  )", $start, $end));
                return $res;
                break;

            case 'ongoing_meetings':
                $res = $wpdb->get_var($wpdb->prepare("
                SELECT
                count(DISTINCT(a.ID))  as count
                FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as b
                ON a.ID = b.post_id
                   AND b.meta_key = 'seeker_path'
                   AND ( b.meta_value = 'ongoing' OR b.meta_value = 'coaching' )
                JOIN $wpdb->dt_activity_log time
                ON
                    time.object_id = a.ID
                    AND time.object_type = 'contacts'
                    AND time.meta_key = 'seeker_path'
                    AND ( time.meta_value = 'ongoing' OR time.meta_value = 'coaching' )
                    AND time.hist_time < %s
                LEFT JOIN $wpdb->postmeta as d
                   ON a.ID=d.post_id
                   AND d.meta_key = 'overall_status'
                LEFT JOIN (
                    SELECT object_id, MAX( c.hist_time ) max_time
                        FROM $wpdb->dt_activity_log c
                        WHERE c.object_type = 'contacts'
                        AND c.meta_key = 'overall_status'
                        AND c.old_value = 'active'
                        GROUP BY c.object_id
                ) close
                ON close.object_id = a.ID
                WHERE a.post_status = 'publish'
                  AND a.post_type = 'contacts'
                  AND ( d.meta_value = 'active' OR close.max_time > %s )
                  AND a.ID NOT IN (
                    SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND  meta_value = 'user'
                    GROUP BY post_id
                )", $end, $start));
                return $res;

            default:
                return 0;
                break;
        }
    }


    public static function new_contact_count(int $start, int $end, int $location)
    {
        global $wpdb;
        $locationQuery = '';
        if (isset($location) && !empty($location) && $location != 1) {
            $locationQuery = "AND ID IN ( SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'location_grid' AND  meta_value = $location
                    )";
        }
        $res = $wpdb->get_var(
            $wpdb->prepare("
                SELECT count(ID) as count
                FROM $wpdb->posts
                WHERE post_type = 'contacts'
                  AND post_status = 'publish'
                  AND post_date >= %s
                  AND post_date < %s
                  $locationQuery
                  AND ID NOT IN (
                    SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND  meta_value = 'user'
                    GROUP BY post_id
                )",
                dt_format_date($start, 'Y-m-d'),
                dt_format_date($end, 'Y-m-d')
            )
        );
        return $res;
    }

    public static function assigned_contacts_count(int $start, int $end, int $location)
    {
        global $wpdb;
        $locationQuery = '';
        if (isset($location) && !empty($location) && $location != 1) {
            $locationQuery = "AND WHERE type.meta_key = 'location_grid' AND  type.meta_value = $location";
        }
        $res = $wpdb->get_var(
            $wpdb->prepare("
                SELECT COUNT( DISTINCT(log.object_id) ) as `value`
                FROM $wpdb->dt_activity_log log
                INNER JOIN $wpdb->postmeta as type ON ( log.object_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                INNER JOIN $wpdb->posts post
                ON (
                    post.ID = log.object_id
                    AND post.post_type = 'contacts'
                    AND post.post_status = 'publish'
                )
                WHERE log.meta_key = 'overall_status'
                $locationQuery
                AND log.meta_value = 'assigned'
                AND log.object_type = 'contacts'
                AND log.hist_time > %s
                AND log.hist_time < %s
            ", $start, $end
            )
        );
        return $res;
    }

    public static function active_contacts_count(int $start, int $end, int $location)
    {
        global $wpdb;
        $locationQuery = '';
        if (isset($location) && !empty($location) && $location != 1) {
            $locationQuery = "AND WHERE type.meta_key = 'location_grid' AND  type.meta_value = $location";
        }
        $res = $wpdb->get_var(
            $wpdb->prepare("
                SELECT COUNT( DISTINCT(log.object_id) ) as `value`
                FROM $wpdb->dt_activity_log log
                INNER JOIN $wpdb->postmeta as type ON ( log.object_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                INNER JOIN $wpdb->posts post
                ON (
                    post.ID = log.object_id
                    AND post.post_type = 'contacts'
                    AND post.post_status = 'publish'
                )
                WHERE log.meta_key = 'overall_status'
                $locationQuery
                AND log.meta_value = 'active'
                AND log.object_type = 'contacts'
                AND log.hist_time > %s
                AND log.hist_time < %s
            ", $start, $end
            )
        );
        return $res;
    }

    /**
     * @param int $start timestamp
     * @param int $end timestamp
     * @return array
     */
    public static function seeker_path_activity(int $start = 0, int $end = 0)
    {
        global $wpdb;
        $res = $wpdb->get_results($wpdb->prepare("
            SELECT COUNT( DISTINCT(log.object_id) ) as `value`, log.meta_value as seeker_path
            FROM $wpdb->dt_activity_log log
            INNER JOIN $wpdb->postmeta as type ON ( log.object_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
            INNER JOIN $wpdb->posts post
            ON (
                post.ID = log.object_id
                AND log.meta_key = 'seeker_path'
                AND log.object_type = 'contacts'
            )
            INNER JOIN $wpdb->postmeta pm
            ON (
                pm.post_id = post.ID
                AND pm.meta_key = 'seeker_path'
            )
            WHERE post.post_type = 'contacts'
            AND log.hist_time > %s
            AND log.hist_time < %s
            AND post.post_status = 'publish'
            GROUP BY log.meta_value
        ", $start, $end), ARRAY_A);

        $field_settings = DT_Posts::get_post_field_settings("contacts");
        $seeker_path_options = $field_settings["seeker_path"]["default"];
        $seeker_path_data = [];
        foreach ($seeker_path_options as $option_key => $option_value) {
            $value = 0;
            foreach ($res as $r) {
                if ($r["seeker_path"] === $option_key) {
                    $value = $r["value"];
                }
            }
            $seeker_path_data[$option_key] = [
                "label" => $option_value["label"],
                "value" => $value
            ];
        }

        return $seeker_path_data;
    }

    /**
     * Get the snapshot for each seeker path at a certain date.
     * @param int $end
     *
     * @return array
     */
    public static function seeker_path_at_date(int $end)
    {
        global $wpdb;
        $res = $wpdb->get_results(
            $wpdb->prepare("
                SELECT count( DISTINCT( log.object_id ) ) as value, log.meta_value as seeker_path
                FROM $wpdb->dt_activity_log log
                JOIN (
                    SELECT MAX( hist_time ) as hist_time, object_id
                    FROM  $wpdb->dt_activity_log
                    WHERE meta_key = 'seeker_path'
                    AND meta_value != 'none'
                    AND hist_time < %d
                    GROUP BY object_id
                ) as b ON (
                    log.hist_time = b.hist_time
                    AND log.object_id = b.object_id
                )
                JOIN $wpdb->dt_activity_log as sl ON (
                    sl.object_type = 'contacts'
                    AND sl.object_id = log.object_id
                    AND sl.meta_key = 'overall_status'
                    AND sl.meta_value = 'active'
                    AND sl.hist_time = (
                        SELECT MAX( hist_time ) as hist_time
                        FROM $wpdb->dt_activity_log
                        WHERE meta_key = 'overall_status'
                        AND hist_time < %d
                        AND object_id = log.object_id
                    )
                )
                WHERE log.meta_key = 'seeker_path'
                AND log.object_id NOT IN (
                    SELECT post_id FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND meta_value = 'user'
                    GROUP BY post_id
                )
                GROUP BY log.meta_value
            ", $end, $end
            ), ARRAY_A
        );
        $field_settings = DT_Posts::get_post_field_settings("contacts");
        $seeker_path_options = $field_settings["seeker_path"]["default"];
        $seeker_path_data = [];
        foreach ($seeker_path_options as $option_key => $option_value) {
            $value = 0;
            foreach ($res as $r) {
                if ($r["seeker_path"] === $option_key) {
                    $value = $r["value"];
                }
            }
            $seeker_path_data[$option_key] = [
                "label" => $option_value["label"],
                "value" => $value
            ];
        }

        return $seeker_path_data;
    }

    /**
     * Get a snapshot of each status at a certain date
     *
     * @param int $end
     *
     * @return array
     */
    public static function overall_status_at_date(int $end)
    {
        global $wpdb;
        $res = $wpdb->get_results(
            $wpdb->prepare("
                SELECT count( DISTINCT( log.object_id ) ) as value, log.meta_value as overall_status
                FROM $wpdb->dt_activity_log log
                INNER JOIN $wpdb->posts post ON (
                    post.ID = log.object_id
                    AND post.post_type = 'contacts'
                )
                JOIN (
                    SELECT MAX( hist_time ) as hist_time, object_id
                    FROM  $wpdb->dt_activity_log
                    WHERE meta_key = 'overall_status'
                    AND hist_time < %d
                    GROUP BY object_id
                ) as b ON (
                    log.hist_time = b.hist_time
                    AND log.object_id = b.object_id
                )
                WHERE log.meta_key = 'overall_status'
                AND log.object_id NOT IN (
                    SELECT post_id FROM $wpdb->postmeta
                    WHERE meta_key = 'type' AND meta_value = 'user'
                    GROUP BY post_id
                )
                GROUP BY log.meta_value
            ", $end
            ), ARRAY_A
        );
        $field_settings = DT_Posts::get_post_field_settings("contacts");
        $overall_status_options = $field_settings["overall_status"]["default"];
        $overall_status_data = [];
        foreach ($overall_status_options as $option_key => $option_value) {
            $value = 0;
            foreach ($res as $r) {
                if ($r["overall_status"] === $option_key) {
                    $value = $r["value"];
                }
            }
            $overall_status_data[$option_key] = [
                "label" => $option_value["label"],
                "value" => $value
            ];
        }

        return $overall_status_data;
    }

    public static function get_contact_statuses($user_id = null)
    {
        global $wpdb;
        $post_settings = DT_Posts::get_post_settings("contacts");
        if ($user_id) {
            $contact_statuses = $wpdb->get_results($wpdb->prepare("
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as status FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'overall_status' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE p.post_type = 'contacts'
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id), ARRAY_A);
        } else {
            $contact_statuses = $wpdb->get_results("
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as status FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'overall_status'
                GROUP BY pm.meta_value
            ", ARRAY_A);
        }
        if ($user_id) {
            $reason_closed = $wpdb->get_results($wpdb->prepare("
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as reason FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'reason_closed' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE p.post_type = 'contacts'
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id), ARRAY_A);
        } else {
            $reason_closed = $wpdb->get_results("
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as reason FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'reason_closed'
                GROUP BY pm.meta_value
            ", ARRAY_A);
        }
        foreach ($reason_closed as &$reason) {
            if (isset($post_settings["fields"]["reason_closed"]['default'][$reason['reason']]['label'])) {
                $reason['reason'] = $post_settings["fields"]["reason_closed"]['default'][$reason['reason']]['label'];
            }
            if ($reason['reason'] === '') {
                $reason['reason'] = "No reason set";
            }
        }
        if ($user_id) {
            $reason_paused = $wpdb->get_results($wpdb->prepare("
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as reason FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'reason_paused' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id), ARRAY_A);
        } else {
            $reason_paused = $wpdb->get_results("
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as reason FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'reason_paused'
                GROUP BY pm.meta_value
            ", ARRAY_A);
        }
        foreach ($reason_paused as &$reason) {
            if (isset($post_settings["fields"]["reason_paused"]['default'][$reason['reason']]['label'])) {
                $reason['reason'] = $post_settings["fields"]["reason_paused"]['default'][$reason['reason']]['label'];
            }
            if ($reason['reason'] === '') {
                $reason['reason'] = "No reason set";
            }
        }
        if ($user_id) {
            $reason_unassignable = $wpdb->get_results($wpdb->prepare("
                SELECT COUNT(pm1.meta_value) as count, pm1.meta_value as reason FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON ( pm.post_id = p.ID AND pm.meta_key = 'assigned_to' AND pm.meta_value = %s )
                INNER JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID AND pm1.meta_key = 'reason_unassignable' )
                INNER JOIN $wpdb->postmeta as type ON ( p.ID = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                GROUP BY pm1.meta_value
            ", 'user-' . $user_id), ARRAY_A);
        } else {
            $reason_unassignable = $wpdb->get_results("
                SELECT COUNT(pm.meta_value) as count, pm.meta_value as reason FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta as type ON ( pm.post_id = type.post_id AND type.meta_key = 'type' AND type.meta_value != 'user' )
                WHERE pm.meta_key = 'reason_unassignable'
                GROUP BY pm.meta_value
            ", ARRAY_A);
        }
        foreach ($reason_unassignable as &$reason) {
            if (isset($post_settings["fields"]["reason_unassignable "]['default'][$reason['reason']]['label'])) {
                $reason['reason'] = $post_settings["fields"]["reason_unassignable "]['default'][$reason['reason']]['label'];
            }
            if ($reason['reason'] === '') {
                $reason['reason'] = "No reason set";
            }
        }

        foreach ($contact_statuses as &$status) {
            if ($status['status'] === 'closed') {
                $status['reasons'] = $reason_closed;
            } elseif ($status['status'] === 'paused') {
                $status['reasons'] = $reason_paused;
            } elseif ($status['status'] === 'unassignable') {
                $status['reasons'] = $reason_unassignable;
            } else {
                $status['reasons'] = [];
            }
            if (isset($post_settings["fields"]["overall_status"]['default'][$status['status']]['label'])) {
                $status['status'] = $post_settings["fields"]["overall_status"]['default'][$status['status']]['label'];
            }
        }
        return $contact_statuses;
    }

    /**
     * Returns count of outreach
     * Primary 'countable'
     *
     * @param string $status
     * @param int $start
     * @param null $end
     *
     */
    public static function get_outreach_count(string $status = '', int $start = 0, $end = null)
    {

        $year = dt_get_year_from_timestamp($start);
        $status = strtolower($status);

        if (empty($year)) {
            $year = gmdate('Y'); // default to this year
        }

        switch ($status) {


            case 'manual_additions':
//                global $wpdb;
//
//                $manual_additions = $wpdb->get_results($wpdb->prepare( "
//                SELECT a.type as source,
//                  h.meta_value as total,
//                  g.meta_value as section
//                FROM $wpdb->dt_reports as a
//                LEFT JOIN $wpdb->dt_reportmeta as e
//                  ON a.id=e.report_id
//                     AND e.meta_key = 'year'
//                LEFT JOIN $wpdb->dt_reportmeta as h
//                  ON a.id=h.report_id
//                     AND h.meta_key = 'total'
//                  LEFT JOIN $wpdb->dt_reportmeta as g
//                    ON a.id=g.report_id
//                       AND g.meta_key = 'section'
//                WHERE type = 'monthly_report'
//                  AND a.id IN ( SELECT MAX( bb.report_id )
//                    FROM $wpdb->dt_reportmeta as bb
//                      LEFT JOIN $wpdb->dt_reportmeta as d
//                        ON bb.report_id=d.report_id
//                           AND d.meta_key = 'source'
//                      LEFT JOIN $wpdb->dt_reportmeta as e
//                        ON bb.report_id=e.report_id
//                           AND e.meta_key = 'year'
//                    WHERE bb.meta_key = 'submit_date'
//                    GROUP BY d.meta_value, e.meta_value
//                  )
//                AND e.meta_value = %s
//                ", $year ), ARRAY_A );

                /*
                    $manual_additions = $wpdb->get_results($wpdb->prepare( "
                    SELECT  e.meta_key as source,
                      e.meta_value as total,
                      ('outreach') as section
                    FROM $wpdb->dt_reports as a
                    LEFT JOIN $wpdb->dt_reportmeta as e
                    ON a.id=e.report_id
                    WHERE type = 'monthly_report';
                    ", $year ), ARRAY_A );
                */


//                $sources = get_option( 'dt_critical_path_sources', [] );
//                $additions = [];
//                foreach ( $sources as $source ){
//                    foreach ( $manual_additions as $addition_i => $addition ){
//                        if ( $source["key"] === $addition["source"] ){
//                            $addition["label"] = $source["label"];
//                            $additions[] = $addition;
//                        }
//                    }
//                }
//                return $additions;
                break;
            default: // countable outreach
//                global $wpdb;
//                $results = $wpdb->get_results( "
//                    SELECT type, report_subsource, max(report_date) as latest_report, meta_value as critical_path_total
//                    FROM $wpdb->dt_reports
//                    INNER JOIN $wpdb->dt_reportmeta rm
//                        ON $wpdb->dt_reports.id = rm.report_id
//                    WHERE focus = 'outreach'
//                        AND meta_key = 'critical_path_total'
//                    GROUP BY type, report_subsource
//                    ORDER BY report_date DESC
//                    ", ARRAY_A );
//                $sum = 0;
//                foreach ( $results as $result ) {
//                    $sum += $result['critical_path_total'];
//                }

//                return $sum;
                break;
        }
    }


    public static function get_monthly_reports_count($start, $end)
    {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT report.id, FROM_UNIXTIME(report.time_end) as report_date, rm.meta_key, rm.meta_value
            FROM $wpdb->dt_reports as report
            JOIN $wpdb->dt_reportmeta as rm ON ( rm.report_id = report.id )
            WHERE report.type = 'monthly_report'
            AND report.time_end >= %s
            AND report.time_end < %s
            ORDER BY report.time_end DESC
        ",
            $start,
            $end
        ), ARRAY_A);
        $sources = get_option('dt_critical_path_sources', []);
        $reports = [];
        foreach ($sources as $source) {
            $reports[$source["key"]] = [
                "label" => $source["label"],
                "section" => $source["section"] ?? 'none',
                "description" => $source["description"] ?? '',
                "sum" => 0,
                "latest" => null
            ];
        }
        foreach ($results as $res) {
            if (isset($reports[$res["meta_key"]])) {
                $reports[$res["meta_key"]]["sum"] += (int)$res["meta_value"];
                if ($reports[$res["meta_key"]]["latest"] === null) {
                    $reports[$res["meta_key"]]["latest"] = (int)$res["meta_value"];
                }
            }
        }

        return $reports;
    }

    /**
     * Returns count of contacts for different statuses
     * Primary 'countable'
     *
     * @param string $status
     * @param int $start
     * @param int $end
     * @param array $args
     *
     * @return int|array
     */
    public static function get_groups_count(string $status, int $start, int $end, $args = [])
    {

        $status = strtolower($status);

        switch ($status) {

            case 'generations':
                return self::get_group_generations($start, $end, $args);
                break;
            case 'church_generations':
                $generations = self::get_group_generations($start, $end);
                $church_generations = [];
                if (!empty($generations)) {
                    foreach ($generations as $gen_key => $gen_val) {
                        if (isset($gen_val["generation"])) {
                            $church_generations[$gen_val["generation"]] = isset($gen_val["church"]) ? $gen_val["church"] : 0;
                        }

                    }
                }

                return $church_generations;
                break;
            case 'churches_and_groups':
                $generations = self::get_group_generations($start, $end);
                $total = 0;
                foreach ($generations as $gen) {
                    $total += $gen["group"] + $gen["church"];
                }
                return $total;
                break;

            case 'active_churches':
                $generations = self::get_group_generations($start, $end);
                $total = 0;
                if (isset($generations)) {
                    foreach ($generations as $gen) {
                        $total += isset($gen["church"]) ? $gen["church"] : 0;
                    }
                }

                return $total;
                break;

            case 'active_groups':
                $generations = self::get_group_generations($start, $end);
                $total = 0;
                foreach ($generations as $gen) {
                    $total += isset($gen["group"]) ? $gen["group"] : 0;
                }
                return $total;
                break;

            case 'church_planters':
                return self::query_church_planters($start, $end);
                break;

            default: // countable contacts
                return 0;
        }
    }


    /**
     * Get group generation of groups that were active in the time range
     * @param $start
     * @param $end
     * @param array $args
     *
     * @return array
     */
    public static function get_group_generations($start, $end, $args = [])
    {
        if (!isset(self::$generations[$start . $end])) {
            $raw_connections = self::query_get_all_group_connections();
            if (is_wp_error($raw_connections)) {
                return $raw_connections;
            }
            $groups_in_time_range = self::query_get_groups_id_list($start, $end, $args);
            $church_generation = self::build_group_generation_counts($raw_connections, 0, 0, [], $groups_in_time_range);
            $generations = [];
            foreach ($church_generation as $k => $v) {
                $generations[] = $v;
            }
            $church_generation = $generations;
            self::$generations[$start . $end] = $church_generation;
            return $church_generation;
        } else {
            return self::$generations[$start . $end];
        }
    }


    public static function query_get_all_group_connections()
    {
        global $wpdb;
        //get all group connections with parent_id, group_id, group_type, group_status
        //first get groups with no parent as parent_id 0
        $results = $wpdb->get_results("
            SELECT
              a.ID         as id,
              0            as parent_id,
              d.meta_value as group_type,
              c.meta_value as group_status
            FROM $wpdb->posts as a
              JOIN $wpdb->postmeta as c
                ON a.ID = c.post_id
                   AND c.meta_key = 'group_status'
              LEFT JOIN $wpdb->postmeta as d
                ON a.ID = d.post_id
                   AND d.meta_key = 'group_type'
            WHERE a.post_status = 'publish'
                  AND a.post_type = 'groups'
                  AND a.ID NOT IN (
                      SELECT DISTINCT (p2p_from)
                      FROM $wpdb->p2p
                      WHERE p2p_type = 'groups_to_groups'
                      GROUP BY p2p_from
                  )
            UNION
            SELECT
              p.p2p_from                          as id,
              p.p2p_to                            as parent_id,
              (SELECT meta_value
               FROM $wpdb->postmeta
               WHERE post_id = p.p2p_from
                     AND meta_key = 'group_type') as group_type,
               (SELECT meta_value
               FROM $wpdb->postmeta
               WHERE post_id = p.p2p_from
                     AND meta_key = 'group_status') as group_status
            FROM $wpdb->p2p as p
            WHERE p.p2p_type = 'groups_to_groups'
        ", ARRAY_A);

        return dt_queries()->check_tree_health($results);
    }


    /**
     * Groups that were active in a date range
     * @param int $start_date
     * @param int $end_date
     * @param array $args
     *
     * @return array
     */
    public static function query_get_groups_id_list($start_date = 0, $end_date = PHP_INT_MAX, $args = [])
    {
        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare("
            SELECT
              a.ID
            FROM $wpdb->posts as a
              JOIN $wpdb->postmeta as status
                ON a.ID = status.post_id
                AND status.meta_key = 'group_status'
              JOIN $wpdb->postmeta as type
                ON a.ID = type.post_id
                AND type.meta_key = 'group_type'
              JOIN $wpdb->postmeta as assigned_to
                ON a.ID = assigned_to.post_id
                AND assigned_to.meta_key = 'assigned_to'
                AND assigned_to.meta_value LIKE %s
              LEFT JOIN $wpdb->postmeta as c
                ON a.ID = c.post_id
                   AND c.meta_key = 'start_date'
              LEFT JOIN $wpdb->postmeta as d
                ON a.ID = d.post_id
                AND d.meta_key = 'end_date'
              LEFT JOIN $wpdb->postmeta as e
                ON a.ID = e.post_id
                AND e.meta_key = 'church_start_date'
            WHERE a.post_type = 'groups'
              AND a.post_status = 'publish'
              AND (
                type.meta_value = 'pre-group'
                OR ( type.meta_value = 'group'
                  AND c.meta_value < %d
                  AND ( status.meta_value = 'active' OR d.meta_value > %d ) )
                OR ( type.meta_value = 'church'
                  AND e.meta_value < %d
                  AND ( status.meta_value = 'active' OR d.meta_value > %d ) )
              )
        ", isset($args['assigned_to']) ? 'user-' . $args['assigned_to'] : '%%', $end_date, $start_date, $end_date, $start_date));

        return $results;
    }


    /**
     * Count group generations by group type
     *
     * @param array $elements
     * @param int $parent_id
     * @param int $generation
     * @param array $counts
     * @param array $ids_to_include
     *
     * @return array
     */
    public static function build_group_generation_counts(array $elements, $parent_id = 0, $generation = 0, $counts = [], $ids_to_include = [])
    {

        $generation++;
        if (!isset($counts[$generation])) {
            $counts[$generation] = [
                "generation" => (string)$generation,
                "pre-group" => 0,
                "group" => 0,
                "church" => 0,
                "total" => 0
            ];
        }
        foreach ($elements as $element) {

            if ($element['parent_id'] == $parent_id) {
                if (in_array($element['id'], $ids_to_include)) {
                    if ($element["group_type"] === "pre-group") {
                        $counts[$generation]["pre-group"]++;
                    } elseif ($element["group_type"] === "group") {
                        $counts[$generation]["group"]++;
                    } elseif ($element["group_type"] === "church") {
                        $counts[$generation]["church"]++;
                    }
                    $counts[$generation]["total"]++;
                }
                $counts = self::build_group_generation_counts($elements, $element['id'], $generation, $counts, $ids_to_include);
            }
        }

        return $counts;
    }

    public static function query_church_planters($start, $end)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT(p2p.p2p_to))
            FROM $wpdb->posts as p
            JOIN $wpdb->postmeta pm ON (
                p.ID = pm.post_id
                AND pm.meta_key = 'church_start_date'
                AND pm.meta_value > %s
                AND pm.meta_value < %s
            )
            JOIN $wpdb->p2p p2p ON (
                p2p.p2p_from = p.ID
                AND p2p.p2p_type = 'groups_to_coaches'
            )
            WHERE p.post_type = 'groups'
        ", $start, $end));
        return $count;
    }


    /**
     * Generations of baptisms which occurred in a time range
     * @param $start
     * @param $end
     *
     * @return array
     */
    public static function get_baptism_generations($start, $end, $location)
    {
        if (!isset(self::$generations[$start . $end])) {
            $raw_baptism_generation_list = self::query_get_all_baptism_connections($location);
            if (is_wp_error($raw_baptism_generation_list)) {
                return $raw_baptism_generation_list;
            }
            $all_baptisms = self::build_baptism_generation_counts($raw_baptism_generation_list);
            $baptism_generations_this_year = self::build_baptism_generations_in_range($all_baptisms, $start, $end);
            //hide extra generations that are only 0;
            for ($i = count($baptism_generations_this_year); $i > 1; $i--) {
                if ($baptism_generations_this_year[$i] === 0 && $i > 1 && $baptism_generations_this_year[$i - 1] === 0) {
                    unset($baptism_generations_this_year[$i]);
                } else {
                    break;
                }
            }
            self::$generations[$start . $end] = $baptism_generations_this_year;
            if (!isset(self::$total[$start . $end])) {
                $total_baptisms = array_sum($baptism_generations_this_year);
                self::$total[$start . $end] = $total_baptisms;
            }
            return $baptism_generations_this_year;
        } else {
            return self::$generations[$start . $end];
        }
    }


    /**
     * Counts the number of baptizers who are not zero generation.
     *
     * @access public
     * @param int $start unix timestamp
     * @param int $end unix timestamp
     *
     * @return int
     * @since  0.1.0
     *
     */
    public static function get_number_of_baptizers(int $start, int $end)
    {
        global $wpdb;

        $results = $wpdb->get_var($wpdb->prepare(
            "SELECT count(DISTINCT(p2p_to)) as count
            FROM $wpdb->p2p
            WHERE p2p_from IN (
              SELECT a.ID
              FROM $wpdb->posts as a
                JOIN $wpdb->postmeta as b
                  ON a.ID = b.post_id
                     AND b.meta_key = 'baptism_date'
                     AND ( b.meta_value >= %s
                           AND b.meta_value < %s )
              WHERE a.post_status = 'publish'
                    AND a.post_type = 'contacts'
            )
            AND p2p_type = 'baptizer_to_baptized'",
            $start, $end));
        return $results;
    }

    /**
     * Baptisms with baptism date in range
     * @param int $start_date
     * @param int $end_date
     *
     * @return array
     */

    public static function query_get_baptisms_id_list($start_date = 0, $end_date = PHP_INT_MAX)
    {
        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare("
            SELECT
              a.ID
            FROM $wpdb->posts as a
              JOIN $wpdb->postmeta as c
                ON a.ID = c.post_id
                   AND c.meta_key = 'baptism_date'
                   AND c.meta_value >= %d
                   AND c.meta_value < %d
            WHERE a.post_type = 'contacts'
                  AND a.post_status = 'publish'
        ", $start_date, $end_date));

        return $results;
    }

    public static function build_baptism_generations_in_range($all_baptisms, $start_date = null, $end_date = null)
    {

        $count = [];
        foreach ($all_baptisms as $k => $v) {
            $count[$k] = 0;
        }

        // get master list of ids for baptisms this year
        $list = self::query_get_baptisms_id_list($start_date, $end_date);

        // redact counts according to baptisms this year
        foreach ($list as $baptism) {
            foreach ($all_baptisms as $generation) {
                if (in_array($baptism, $generation["ids"])) {
                    if (!isset($count[$generation["generation"]])) {
                        $count[$generation["generation"]] = 0;
                    }
                    $count[$generation["generation"]]++;
                }
            }
        }
        if (isset($count[0])) {
            unset($count[0]);
        }

        // return counts
        return $count;
    }

    public static function query_get_all_baptism_connections($location)
    {
        global $wpdb;
        $locationQuery = '';
        if (isset($location) && !empty($location) && $location != 1) {
            $locationQuery = "AND ID IN ( SELECT post_id
                    FROM $wpdb->postmeta
                    WHERE meta_key = 'location_grid' AND  meta_value = $location
                    )";
        }
        //get baptizers with no parent as parent_id 0
        //get all other baptism connects with id and parent_id
        $results = $wpdb->get_results("
            SELECT
                a.ID as id,
                0    as parent_id
            FROM $wpdb->posts as a
            WHERE a.post_type = 'contacts'
                AND a.post_status = 'publish'
                AND a.ID NOT IN (
                    SELECT
                    DISTINCT( b.p2p_from ) as id
                    FROM $wpdb->p2p as b
                    WHERE b.p2p_type = 'baptizer_to_baptized'
                )
                AND a.ID IN (
                    SELECT
                    DISTINCT( b.p2p_to ) as id
                    FROM $wpdb->p2p as b
                    WHERE b.p2p_type = 'baptizer_to_baptized'
                )
                $locationQuery
            UNION
            SELECT
                b.p2p_from as id,
                b.p2p_to as parent_id
            FROM $wpdb->p2p as b
            WHERE b.p2p_type = 'baptizer_to_baptized'
        ", ARRAY_A);

        return dt_queries()->check_tree_health($results);
    }

    public static function build_baptism_generation_counts(array $elements, $parent_id = 0, $generation = -1, $counts = [])
    {

        $generation++;
        if (!isset($counts[$generation])) {
            $counts[$generation] = [
                "generation" => (string)$generation,
                "total" => 0,
                "ids" => []
            ];
        }
        foreach ($elements as $element_i => $element) {
            if ($element['parent_id'] == $parent_id) {
                //find and remove if the baptisms has already been counted on a longer path
                //we keep the shorter path
                $already_counted_in_deeper_path = false;
                foreach ($counts as $count_i => $count) {
                    if ($count_i > $generation) {
                        if (in_array($element['id'], $count["ids"])) {
                            $counts[$count_i]["total"]--;
                            unset($counts[$count_i]["ids"][array_search($element['id'], $count["ids"])]);
                        }
                    } else {
                        if (in_array($element['id'], $count["ids"])) {
                            $already_counted_in_deeper_path = true;
                        }
                    }
                }
                if (!$already_counted_in_deeper_path) {
                    $counts[$generation]["total"]++;
                    $counts[$generation]["ids"][] = $element['id'];
                }
                $counts = self::build_baptism_generation_counts($elements, $element['id'], $generation, $counts);
            }
        }

        return $counts;
    }

    /*
     * Save baptism generation number on all contact who have been baptized.
     */
    public static function save_all_contact_generations()
    {
        $raw_baptism_generation_list = self::query_get_all_baptism_connections();
        if (is_wp_error($raw_baptism_generation_list)) {
            return $raw_baptism_generation_list;
        }
        $all_baptisms = self::build_baptism_generation_counts($raw_baptism_generation_list);
        foreach ($all_baptisms as $baptism_generation) {
            $generation = $baptism_generation["generation"];
            $baptisms = $baptism_generation["ids"];
            foreach ($baptisms as $contact) {
                update_post_meta($contact, 'baptism_generation', $generation);
            }
        }
    }

    /*
     * Set baptisms generation counts on a contact's baptism tree
     * Check parent's baptism generation and cascade to children
     * $parent_ids array is used to avoid infinite loops.
     */
    public static function reset_baptism_generations_on_contact_tree($contact_id, $parent_ids = [])
    {
        global $wpdb;
        $parents = $wpdb->get_results($wpdb->prepare("
            SELECT contact.ID as contact_id, gen.meta_value as baptism_generation
            FROM $wpdb->p2p as b
            JOIN $wpdb->posts as contact ON ( contact.ID = b.p2p_to )
            LEFT JOIN $wpdb->postmeta gen ON ( gen.post_id = contact.ID AND gen.meta_key = 'baptism_generation' )
            WHERE b.p2p_type = 'baptizer_to_baptized'
            AND b.p2p_from = %s
        ", $contact_id), ARRAY_A);

        $highest_parent_gen = 0;
        foreach ($parents as $parent) {
            if (empty($parent["baptism_generation"]) && $parent["baptism_generation"] != "0") {
                return self::reset_baptism_generations_on_contact_tree($parent["contact_id"]);
            } else if ($parent["baptism_generation"] > $highest_parent_gen) {
                $highest_parent_gen = $parent["baptism_generation"];
            }
            $parent_ids[] = $parent["contact_id"];
        }
        $parent_ids[] = $contact_id;

        $current_saved_gen = get_post_meta($contact_id, 'baptism_generation', true);
        if ((int)$current_saved_gen != ((int)$highest_parent_gen) + 1) {
            if (sizeof($parents) == 0) {
                update_post_meta($contact_id, 'baptism_generation', "0");
            } else {
                update_post_meta($contact_id, 'baptism_generation', $highest_parent_gen + 1);
            }
            $children = $wpdb->get_results($wpdb->prepare("
                SELECT contact.ID as contact_id, gen.meta_value as baptism_generation
                FROM $wpdb->p2p as b
                JOIN $wpdb->posts as contact ON ( contact.ID = b.p2p_from )
                LEFT JOIN $wpdb->postmeta gen ON ( gen.post_id = contact.ID AND gen.meta_key = 'baptism_generation' )
                WHERE b.p2p_type = 'baptizer_to_baptized'
                AND b.p2p_to = %s
            ", $contact_id), ARRAY_A);
            foreach ($children as $child) {
                if (!in_array($child["contact_id"], $parent_ids)) {
                    self::reset_baptism_generations_on_contact_tree($child["contact_id"], $parent_ids);
                }
            }
        }
    }

    /**
     * Counts the number of contacts with no disciples in database
     *
     * @access public
     * @param $start
     * @param $end
     *
     * @return float|int
     * @since  0.1.0
     *
     */
    public static function get_number_of_baptisms($start, $end, $location)
    {

        if (!isset(self::$total[$start . $end])) {
            $baptism_generations_this_year = self::get_baptism_generations($start, $end, $location);
            if (is_wp_error($baptism_generations_this_year)) {
                return 0;
            }
            $total_baptisms = array_sum($baptism_generations_this_year);
            self::$total[$start . $end] = $total_baptisms;
            return $total_baptisms;
        } else {
            return self::$total[$start . $end];
        }
    }
}
