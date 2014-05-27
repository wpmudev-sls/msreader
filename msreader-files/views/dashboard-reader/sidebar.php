<?php
do_action('msreader_dashboard_reader_sidebar_top');

$sidebar_widgets = apply_filters('msreader_dashboard_reader_sidebar_widgets', 
	array(
		'reader' => array(
				'title' => apply_filters('msreader_dashboard_reader_sidebar_widget_reader_title', 'Reader'),
				'data' => array(
						'links' => array()
					)
			)
	)
);

foreach ($sidebar_widgets as $slug => $details) {
	//open default styling
	$default_style = (!isset($details['default_style']) || (isset($details['default_style']) && $details['default_style'])) ? 1 : 0;
	if($default_style) { 
	?>
		<div id="msreader-widget-<?php echo $slug; ?>" class="msreader-widget postbox">
			<?php echo isset($details['title']) ? '<h3>'.$details['title'].'</h3>' : ''; ?>
			<div class="inside">
	<?php 
	}
	else {
	?>
		<div id="msreader-widget-<?php echo $slug; ?>" class="msreader-widget">
	<?php
	}
	
	//echo widget data
	if(isset($details['data']) && is_array($details['data']))
		foreach ($details['data'] as $type => $content) {
			//echo as links if links
			if($type == 'list' && isset($content) && count($content) > 0) {
				echo '<ul class="list">';
				foreach ($content as $priority => $value) {
					if(!isset($value['title']))
						continue;
					
					//check for active url so class can be added
					$link_query = parse_url($value['link']);
					$link_query = isset($link_query['query']) ? $link_query['query'] : '';
					if(isset($value['link'])){
						$active = (strpos($_SERVER['QUERY_STRING'], $link_query) !== false || (strpos($link_query,'module='.$this->plugin['site_options']['default_module']) !== false) && !isset($_GET['module'])) ? ' class="active"' : '';
						echo '<li'.$active.'>'.(isset($value['before']) ? $value['before'] : '').'<a href="'.$value['link'].'">'.$value['title'].'</a>'.(isset($value['after']) ? $value['after'] : '').'</li>';
					}
					else
						echo '<li>'.(isset($value['before']) ? $value['before'] : '').$value['title'].(isset($value['after']) ? $value['after'] : '').'</li>';
				}
				echo '</ul>';
			}
			//echo as html by default
			else
				echo $type == 'html' ? $content : '';
		}
	elseif(isset($details['data']))
		echo $details['data'];

	
	//close default styling
	if($default_style) { ?>
			</div>
		</div>	
	<?php
	}
	else {
	?>
		</div>
	<?php
	}
}

do_action('msreader_dashboard_reader_sidebar_bottom');