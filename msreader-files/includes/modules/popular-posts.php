<?php
$module = array(
	'name' => __( 'Popular Posts', 'wmd_msreader' ),
	'description' => __( 'Displays popular posts', 'wmd_msreader' ),
	'slug' => 'popular_posts', 
	'class' => 'WMD_MSReader_Module_PopularPost'
);

class WMD_MSReader_Module_PopularPost extends WMD_MSReader_Modules {
	function init() {
		add_filter( 'msreader_dashboard_reader_sidebar_widgets', array($this,'add_link_to_widget'), 30 );
    }

    function add_link_to_widget($widgets) {
		$widgets['reader']['data']['links'][] = $this->create_link_for_main_widget();

    	return $widgets;
    }

    function query() {
        $current_user_id = get_current_user_id();
        $limit = $this->get_limit();
        $limit_sample = $this->get_limit($this->limit_sample,1);
        
    	$query = "
            SELECT BLOG_ID, ID, post_author, post_date_gmt, post_date, post_content, post_title, comment_count
            FROM (
                SELECT posts.BLOG_ID AS BLOG_ID, ID, post_author, post_date, post_date_gmt, post_content, post_title, comment_count
                FROM $this->db_network_posts AS posts
                INNER JOIN $this->db_blogs AS blogs ON blogs.blog_id = posts.BLOG_ID
                WHERE blogs.public = 1 AND blogs.archived = 0 AND blogs.spam = 0 AND blogs.deleted = 0
                AND post_status = 'publish'
                AND post_password = ''
                ORDER BY post_date_gmt DESC
                $limit_sample
            ) a
            WHERE comment_count > 8
            ORDER BY post_date_gmt DESC
            $limit
        ";
        $query = apply_filters('msreader_'.$this->details['slug'].'_query', $query, $this->args, $limit, $limit_sample);
        $posts = $this->wpdb->get_results($query);

    	return $posts;
    }
}