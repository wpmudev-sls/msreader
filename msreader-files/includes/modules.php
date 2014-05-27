<?php
//Class with default functions for all modules. Fast to use and easy to customize
abstract class WMD_MSReader_Modules {
	var $module;

    var $wpdb;
    var $db_network_posts;

    var $cache_init;
    var $page;
    var $limit;
    var $limit_sample;
    var $args;

    var $options;

    var $message;
    var $message_type;

	function __construct($options = array()) {
		global $msreader_available_modules, $wpdb;

		//set module details
        end($msreader_available_modules);
		$this->details = $msreader_available_modules[key($msreader_available_modules)];

        //set options for module
        $this->options = $options;

		//sets default unnecessary data
		if(!isset($this->details['page_title']))
			$this->details['page_title'] = $this->details['name'];
		if(!isset($this->details['menu_title']))
			$this->details['menu_title'] = $this->details['name'];

        //set DB details
        $this->wpdb = $wpdb;
        $this->db_network_posts = apply_filters('msreader_db_network_posts', $this->wpdb->base_prefix.'network_posts');
        $this->db_network_terms = apply_filters('msreader_db_network_terms', $this->wpdb->base_prefix.'network_terms');
        $this->db_network_term_rel = apply_filters('msreader_db_network_relationships', $this->wpdb->base_prefix.'network_term_relationships');
        $this->db_network_term_tax = apply_filters('msreader_db_network_taxonomy', $this->wpdb->base_prefix.'network_term_taxonomy');
        $this->db_blogs = $this->wpdb->base_prefix.'blogs';
        $this->db_users = $this->wpdb->base_prefix.'users';

		//do the custom init by module
		$this->init();
    }
    abstract function init();

    //This function needs to be replaced to display proper data - data is automatically cached for this one
    function query() {
		return 'error';
    }

    function get_featured_media_html($post) {
        $post_content = apply_filters('the_content', $post->post_content);
        $content_images_starts = explode('<img', $post_content);

        if(isset($content_images_starts[1]) && $content_images_starts[1]){
            $content_image_ends = explode('/>', $content_images_starts[1]);
            if(isset($content_image_ends[0]) && $content_image_ends[0])
                $content_media = '<img'.$content_image_ends[0].'/>';
        }
        $content_iframe_starts = explode('<iframe', $post_content);

        if($content_iframe_starts && strlen($content_iframe_starts[0]) < strlen($content_images_starts[0])){
            $content_iframe_ends = explode('</iframe>', $content_iframe_starts[1]);
            if($content_iframe_ends[0])
                $content_media = '<iframe'.$content_iframe_ends[0].'</iframe>';
        }

        if(isset($content_media))
            return '<div class="msreader_featured_media"><center>'.$content_media.'</center></div>';

        return '';
    }

    function get_excerpt($post) {
        $max_sentences = 5;
        $max_words = 175;
        $max_paragraphs = 3;

        if(class_exists('DOMDocument')) {
            $allowed_tags = array('<strong>','<blockquote>','<em>','<p>', '<span>', '<a>');
            
            $post_content = strip_tags($post->post_content, implode('', $allowed_tags));
            $post_content = apply_filters('the_content', $post_content);
            
            $dom = new DOMDocument();
            $dom->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>'.$post_content.'</body></html>');
            $elements = $dom->documentElement;
            $all_elements = $elements->getElementsByTagName('*');

            $current_paragraphs = 0;
            $current_sentences = 0;
            $limit_reached = 0;
            $remove_childs = array();
            foreach($all_elements as $key => $child) {
                if($child->nodeName == 'html' || $child->nodeName == 'body' || $child->nodeName == 'meta' || $child->nodeName == 'head')
                    continue;

                if($limit_reached || str_replace(array(' ', '&nbsp;'), '', trim($child->textContent)) == '')
                    $remove_childs[] = $child;
                else {
                    //count sentences 
                    $content_sentences = explode('.', $child->textContent);
                    $count_content_sentences = count($content_sentences);

                    if($count_content_sentences) {
                        //ditch fake sentences
                        $count_fake_sentences = 0;
                        foreach ($content_sentences as $sentence) {
                            $sentence_length = strlen($sentence);
                            if(!$sentence || strlen(str_replace (' ', '', $sentence)) == $sentence_length )
                                $count_fake_sentences ++;
                        }
                        $current_sentences = $current_sentences + $count_content_sentences - $count_fake_sentences;
                    }
                    else
                        if(str_word_count($child->textContent) > 3)
                            $current_sentences ++;

                    //count paragraph
                    if($child->nodeName == 'p')
                        $current_paragraphs ++;

                    //check if limit reached
                    if(!$limit_reached && ($current_paragraphs >= $max_paragraphs || $current_sentences >= $max_sentences)) {
                        $last_child = $child;
                        $limit_reached = 1;
                    }
                }
            }
            foreach ($remove_childs as $child) {
                $child->parentNode->removeChild($child);
            }

            $return = $dom->saveHTML();
            if($limit_reached)
                $return .= '...';   
        }
        else {
            $allowed_tags = array('<strong>','<blockquote>','<em>','<p>', '<span>');

            $post_content = strip_tags($post->post_content, implode('', $allowed_tags));
            $post_content = apply_filters('the_content', $post_content);

            $content_sentences = explode('.', strip_tags($post_content, implode('',$allowed_tags)));
            
            //ditch fake sentences
            $count_fake_sentences = 0;
            foreach ($content_sentences as $sentence) {
                $sentence_length = strlen($sentence);
                if(
                    !$sentence || 
                    strlen(str_replace (' ', '', $sentence)) == $sentence_length
                )
                    $count_fake_sentences ++;
            }

            //limit to max sentences
            $return = implode('.', array_slice($content_sentences, 0, $max_sentences + $count_fake_sentences));

            //limit to total word count
            $words = explode(" ",strip_tags($return));
            if(count($words) > $max_words)
                $return = implode(" ",array_slice($words,0,$max_words));

            //check if content was stripped
            if(count($content_sentences)-$count_fake_sentences > $max_sentences || count($words) > $max_words)
                $return .= '...';   

            //close all allowed tags
            foreach ($allowed_tags as $tag) {
                $closing_tag = str_replace('<', '</', $tag);
                $open_close_difference = substr_count($return, $tag) - substr_count($return, $closing_tag);
                for($i =0; $i < $open_close_difference; $i++)
                    $return .=  $closing_tag;
            }
        }

        return $return;
    }

    //get limit string
    function get_limit($limit = 0, $page = 0) {
        $limit = !$limit ? $this->limit : $limit;
        $page = !$page ? $this->page : $page;

        if(is_numeric($limit) && is_numeric($page)) {
            $start = ($limit*$page)-$limit;

            return 'LIMIT '.$start.','.$limit;
        }
        else
            return 'LIMIT 0,10';
    }

    //by default page title is module title
    function get_page_title() {
		return $this->details['page_title'];
    }

    //get limit string
    function get_module_dashboard_url($args = array(), $module_slug = '') {
        $module_slug = $module_slug ? $module_slug : $this->details['slug'];

        $url = admin_url('index.php?page=msreader.php&module='.$module_slug);
        if(is_array($args) && count($args) > 0)
            $url = add_query_arg(array('args' => $args), $url);

        $url = apply_filters('msreader_module_dashboard_url_'.$this->details['slug'], $url, $args);
        $url = apply_filters('msreader_module_dashboard_url', $url, $args);

        return $url;
    }

    //easily adds link to main widget
    function create_link_for_main_widget() {
		$link = array(
				'title' => $this->details['menu_title'], 
				'link' => add_query_arg(array('module' => $this->details['slug'], 'args' => false))
			);

		return $link;
    }

    //lets you create links widget for module by providing array with arrays with "arg"(argument that will be added at the end), "title" or optionaly full link by "link"
    function create_list_widget($links, $widget_details = array()) {
    	foreach ($links as $position => $data) {
    		if(isset($data['args'])){
    			$data['link'] = add_query_arg(array('module' => $this->details['slug'], 'args' => $data['args']));
    			$links[$position] = $data;
    		}
    	}
		$widget = array(
    		'title' => $this->details['menu_title'], 
    		'data' => array(
    			'list' => $links
    		)
    	);

        $widget = array_replace_recursive($widget, $widget_details);

		return $widget;
    }
} 