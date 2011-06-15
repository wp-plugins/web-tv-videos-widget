<?php

/*

Plugin name:  Web-TV Videos Widget
Plugin URI:   http://www.tvbookmark.info/articles/?page_id=334
Description:  A widget to display titles and links to the latest videos from TV channels and newssites (de/en) about a self-chosen topic in the sidebar.
Version:      1.1
Author:       Holger Drewes
Author URI:   http://www.tvbookmark.info

Copyright 2011  Holger Drewes  (email : Holger.Drewes@googlemail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/ 


class TVBookmarkWidget extends WP_Widget {

	
	private $api_url = 'http://www.tvbookmark.de/api/';	


	function TVBookmarkWidget() {

		$widget_ops = array('classname' => 'TVBookmarkWidget',
			'description' => 'Display list of videos from TV channels and newssites for a chosen topic.');	
		parent::WP_Widget('TVBookmarkWidget', 'Web-TV Videos Widget', $widget_ops);
	}

	

	static function getAPIUser() {

		$user = '';		

		$url = 'http://www.tvbookmark.de/api/get_user?type=WORDPRESS';
		$res_str = wp_remote_fopen($url);

		if(mb_substr($res_str, 0, 5) == 'USER:') {
			$user = mb_substr($res_str, 6);
		}

		return $user;
	}



	function renewTVBookmarkData($search_term, $whole_episodes, $category, $language, $rows) {

		global $wpdb;		
		$table_name = $wpdb->prefix.'tvbookmark';
		
		// Update every 30 minutes (60 sec * 30 = 1800)
		// Please don't lower this value. Since the amount of requests possible per day is limited on the server side (60/day),
		// the widget won't work properly any more then, stopping to update after some time during the day, depending on the decrease,
		// cause the daily limit will be used too early. 
		if(time() - get_option('last_update') <= 1800) {
			return;
		}

		update_option('last_update', time());

		/* Read XML via TVBookmark API */
		$search_term = urlencode($search_term);
		$search_term = str_replace('%2B', '+', $search_term);
		if($whole_episodes) {
			$wp = '1';
		} else {
			$wp = '0';
		}

		$cat_add = '';
		if(mb_strlen($category) == 1) {
			$cat_add = '&mc='.$category;
		} else if(mb_strlen($category) == 2) {
			$cat_add  = '&mc='.mb_substr($category, 0, 1);
			$cat_add .= '&c='.mb_substr($category, 1, 1);
		}

		$url = $this->api_url.'search?key='.get_option('api_user').'&q='.$search_term.'&wp='.$wp.$cat_add.'&rows='.$rows.'&lang='.$language.'&format=xml';
		$xml = wp_remote_fopen($url);							
		
		if(!$xml || mb_substr($xml, 0, 5) == 'ERROR') return false;	

		$dom = new DOMDocument();
		$dom->loadXML($xml);
		
		$xp = new domxpath($dom);
		
		$videos = $xp->query("/tvbookmark/results/video");

		if(count($videos) == 0) return false;

		/* Delete old and save new videos in WordPress DB */
		$sql = "DELETE FROM `".$table_name."`";
		$results = $wpdb->query($sql);

		foreach($videos as $video) {
			$title = $xp->query('./title', $video)->item(0)->nodeValue;
			$show_title = $xp->query('./show_title', $video)->item(0)->nodeValue;
			$channel = $xp->query('./channel', $video)->item(0)->nodeValue;
			$title = $title.' - '.$show_title.' ('.$channel.')';
			$description = $xp->query('./description', $video)->item(0)->nodeValue;
			$url = $xp->query('./url', $video)->item(0)->nodeValue;
			
			$title = strip_tags($title);
			$description = strip_tags($description);
			$url = strip_tags($url);

			$data = array('title' => $title, 'description' => $description, 'url' => $url);
			$wpdb->insert($table_name, $data);
		}
	}



	function widget($args, $instance) {
	
		global $wpdb;
      $table_name = $wpdb->prefix.'tvbookmark';

		extract($args);

		$title = apply_filters('widget_title', $instance['title']);
		$search_term = $instance['search_term'];
		$whole_episodes = $instance['whole_episodes'];	
		$category = $instance['category'];
		$title = str_replace('[SEARCHTERM]', $search_term, $title);
		$language = $instance['language'];		
		$rows = $instance['rows'];

		$this->renewTVBookmarkData($search_term, $whole_episodes, $category, $language, $rows);

		echo $before_widget;
		
		echo $before_title;
		echo esc_attr($title);
		echo $after_title;

		/* Display list with videos */
		
		echo '<ul>';	
		$videos = $wpdb->get_results("SELECT * FROM ".$table_name);
		
		foreach($videos as $video) {
			echo '<li><a href="'.esc_url($video->url).'" ';
			
			if($instance['new_tab']) {
				echo 'target="_blank"';
			}
			echo '>';
			echo esc_attr($video->title);
			echo '</a></li>';
		}
		
		echo '</ul>';
		
		if($language == 'de') {
			$search_text = 'Suche auf';
			$search_base_url = 'http://www.tvbookmark.de';
		} else {
			$search_text = 'Search on';
			$search_base_url = 'http://www.tvbookmark.info';
		}
		$search_term = urlencode($search_term);
		$search_term = str_replace('%2B', '+', $search_term);
		if($whole_episodes) {
      	$wp = '1';
		} else {
			$wp = '0';
      }
		
		$cat_add = '';
      if(mb_strlen($category) == 1) {
         $cat_add = '&mc='.$category;
      } else if(mb_strlen($category) == 2) {
         $cat_add  = '&mc='.mb_substr($category, 0, 1);
         $cat_add .= '&c='.mb_substr($category, 1, 1);
      }

		if($instance['show_search_url']) {
			$search_url = esc_url($search_base_url.'/search?q='.$search_term.'&wp='.$wp.$cat_add.'&lang='.$language);
			echo '<div style="margin-top:5px;font-size:10px;">'.$search_text.' <a href="'.$search_url.'" target="_blank">TVBookmark</a></div>';
		}

		echo $after_widget;
	}



	function getCategorySelectBox($category) {

		/* Read categories from API */
		$options = array();
		$options[] = array('ALL', 'All Categories');
		
		$url = $this->api_url.'categories?key='.get_option('api_user').'&format=xml';
      $xml = wp_remote_fopen($url);

      if(!$xml || mb_substr($xml, 0, 5) == 'ERROR') return false;

      $dom = new DOMDocument();
      $dom->loadXML($xml);

      $xp = new domxpath($dom);

      $main_cats = $xp->query("/tvbookmark/results/main_category");
      if(count($main_cats) == 0) return false;

      foreach($main_cats as $main_cat) {
         $main_cat_li = $xp->query('./letter_ident', $main_cat)->item(0)->nodeValue;
			$name = $xp->query('./name/en', $main_cat)->item(0)->nodeValue;
			
			$options[] = array($main_cat_li, '--- '.$name.' ---');

			/* Read out categories for main_category */
			$url = $this->api_url.'categories?key='.get_option('api_user').'&mc='.$main_cat_li.'&format=xml';
      	$xml = wp_remote_fopen($url);

      	if(!$xml || mb_substr($xml, 0, 5) == 'ERROR') return false;

      	$dom2 = new DOMDocument();
      	$dom2->loadXML($xml);

      	$xp2 = new domxpath($dom2);

      	$cats = $xp2->query("/tvbookmark/results/category");
      	if(count($cats) == 0) return false;

			foreach($cats as $cat) {
         	$cat_li = $xp2->query('./letter_ident', $cat)->item(0)->nodeValue;
         	$cat_name = $xp2->query('./name/en', $cat)->item(0)->nodeValue;
         
         	$options[] = array($main_cat_li.$cat_li, $cat_name);
			}

		}

		$sel_box = '';

		$sel_box .= '<select id="'.$this->get_field_id('category').'" name="'.$this->get_field_name('category').'">';

      foreach($options as $option) {
         $sel_box .= '<option value="'.$option[0].'" ';

         if($option[0] == $category) {
            $sel_box .= 'selected';
         }
         $sel_box .= '>'.htmlspecialchars($option[1], ENT_QUOTES, 'UTF-8').'</option>';
      }
      $sel_box .= '</select>';

		return $sel_box;
	}



	function update($new_instance, $old_instance) {

		$instance['title'] = strip_tags($new_instance['title']);
		$instance['search_term'] = strip_tags($new_instance['search_term']);
		
		if($new_instance['category']) {
			$instance['category'] = strip_tags($new_instance['category']);
		} else {
			$instance['category'] = $old_instance['category'];
		}
		$instance['language'] = $new_instance['language'];
		$instance['rows'] = $new_instance['rows'];
		$instance['new_tab'] = $new_instance['new_tab'];
		$instance['whole_episodes'] = $new_instance['whole_episodes'];
		$instance['show_search_url'] = $new_instance['show_search_url'];		

		update_option('last_update', strtotime("-1 week"));

		return $instance;
	}



	function form($instance) {
		
		$defaults = array('title' => 'Videos: [SEARCHTERM]', 'category' => 'ALL', 'language' => 'en', 'rows' => 5, 'new_tab' => true);
		$instance = wp_parse_args((array) $instance, $defaults);		

		$title = esc_attr($instance['title']);
		$search_term = esc_attr($instance['search_term']);
		
		// Try to reload user if not existant yet
		$api_user = get_option('api_user');
		if($api_user == '') {
			$api_user = self::getAPIUser();
			update_option('api_user', $api_user);
		}

		// Title
		echo '<p>';
		echo '<label for="'.$this->get_field_id('title').'">Title:</label> ';
		echo '<input id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" ';
		echo 'value="'.$title.'" size="25" /><br />';
		echo '<span class="description">Note: If you use the placeholder [SEARCHTERM] in your title, it will be replaced by your chosen search term(s).</span>';
		echo '</p>';

		// Search Term
		echo '<p>';
		echo '<label for="'.$this->get_field_id('search_term').'">Search Term(s):</label> ';
		echo '<input id="'.$this->get_field_id('search_term').'" name="'.$this->get_field_name('search_term').'" ';
		echo 'value="'.$search_term.'" style="width:100%"/>';
		echo '<span class="description">Note: One or more space-separated words (e.g. &quot;Apple&quot;, &quot;Steve Jobs&quot;), you can also separate with &quot;OR&quot; to ';
		echo 'find videos with only one of the terms (e.g. &quot;iPod OR iPad OR Mac OR iTunes&quot;) </span>';
		echo '</p>';

		// Category
		$cat_sel_box = $this->getCategorySelectBox($instance['category']);

		echo '<p>';
		echo '<label for="'.$this->get_field_id('category').'">Category:</label> ';
		
		if($cat_sel_box) {		
			echo $cat_sel_box.'<br />';
			echo '<span class="description">Note: For displaying videos about a general topic like &quot;News&quot;, &quot;Sports&quot; or &quot;Soccer&quot; choose ';
			echo 'a category (e.g. &quot;Sports&quot;) or subcategory (e.g. &quot;Soccer&quot;) instead ';
			echo 'of typing your topic in the search term box for better results. A search term can also be combined with a category (e.g. &quot;iPhone&quot; videos only from ';
			echo '&quot;Technology&quot; category).</span>';
		} else {
			echo '<span style="color:red">The selectable categories could not be read from TVBookmark. Please try again after a couple of minutes. ';
			echo 'Your previous category selection will be preserved!</span>';
		}
		echo '</p>';

		// Whole episodes
		echo '<p>';
		echo '<label for="'.$this->get_field_id('whole_episodes').'">Only whole episodes:</label> ';
		echo '<input class="checkbox" type="checkbox" id="'.$this->get_field_id('whole_episodes').'" name="'.$this->get_field_name('whole_episodes').'" ';
		
		if($instance['whole_episodes'] == true) {
         echo 'checked';
      }
      echo ' />';
      echo '</p>';

		// Language
		echo '<p>';
		echo '<label for="'.$this->get_field_id('language').'">Language for videos:</label> ';
		echo '<select id="'.$this->get_field_id('language').'" name="'.$this->get_field_name('language').'">';
		
		$options = array();
		$options[] = array("en", "English");
		$options[] = array("de", "German");

		foreach($options as $option) {
			echo '<option value="'.$option[0].'" ';
		
			if($option[0] == $instance['language']) {
				echo 'selected';
			}
			echo '>'.$option[1].'</option>';
		}
		echo '</select>';
		echo '</p>';

		// Rows
		echo '<p>';
		echo '<label for="'.$this->get_field_id('rows').'">Number of videos:</label> ';
		echo '<select id="'.$this->get_field_id('rows').'" name="'.$this->get_field_name('rows').'">';

		for($i=1;$i<=10;$i++) {
			echo '<option value="'.$i.'" ';
			
			if($i == $instance['rows']) {
				echo 'selected';
			}
			echo '>'.$i.'</option>';
		}
		echo '</select>';
		echo '</p>';

		// New Tab
		echo '<p>';
		echo '<label for="'.$this->get_field_id('new_tab').'">Open videos in new tab:</label> ';
		echo '<input class="checkbox" type="checkbox" id="'.$this->get_field_id('new_tab').'" name="'.$this->get_field_name('new_tab').'" ';
		
		if($instance['new_tab'] == true) {
			echo 'checked';
		}
		echo ' />';
		echo '</p>';
			
		// Search URL
		echo '<p>';
		echo '<span style="color:red">Since this plugin lives from getting some traffic back to our site, please consider to activate the following search link if you like it! Thanks!</span><br />';		
		echo '<label for="'.$this->get_field_id('show_search_url').'">Show &quot;Search on TVBookmark&quot; link:</label> ';
		echo '<input class="checkbox" type="checkbox" id="'.$this->get_field_id('show_search_url').'" name="'.$this->get_field_name('show_search_url').'" ';

      if($instance['show_search_url'] == true) {
         echo 'checked';
      }
      echo ' />'; 
		echo '</p>';

		// API-Key
		if($api_user == '') {
			echo '<p>';
			echo '<span style="color:red">The necessary key to get the video data from the remote site could not be retrieved. ';
			echo 'The retrieval process is retried every time you open this options page, so please wait a couple of minutes and retry. ';
			echo 'If that doesn\'t help please try again later.</span>';
			echo '</p>';
		}
	}

	
	
	function install() {

		global $wpdb, $user_ID;
		$table_name = $wpdb->prefix.'tvbookmark';

		/* Create table if table doesn't exist yet */
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			
			$sql = "CREATE TABLE `".$table_name."` (
					`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`title` varchar(255) character set utf8 NOT NULL,
					`description` text character set utf8,
					`url` text character set utf8 NOT NULL
					)";
			$results = $wpdb->query($sql);
			// Test data
			$data = array('title' => 'Test Video', 'url' => 'http://www.testurl.de');
			$wpdb->insert($table_name, $data);
		}

		/* Get API user */
		add_option('api_user', self::getAPIUser());
		
		/* Initiaize other options */
		add_option('last_update', strtotime("-1 week"));
	}	

}


add_action('widgets_init', create_function('', 'return register_widget("TVBookmarkWidget");'));
register_activation_hook(__FILE__, array('TVBookmarkWidget', 'install'));

?>
