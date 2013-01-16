<?php 

/**
	 * Registers and handles diPH Project functions
	 *
	 * @package diPH Toolkit
	 * @author diPH Team
	 * @link http://diph.org/download/
	 */


function diph_project_init() {
  $labels = array(
    'name' => _x('Projects', 'post type general name'),
    'singular_name' => _x('Project', 'post type singular name'),
    'add_new' => _x('Add New', 'project'),
    'add_new_item' => __('Add New Project'),
    'edit_item' => __('Edit Project'),
    'new_item' => __('New Project'),
    'all_items' => __('Projects'),
    'view_item' => __('View Project'),
    'search_items' => __('Search Projects'),
    'not_found' =>  __('No projects found'),
    'not_found_in_trash' => __('No projects found in Trash'), 
    'parent_item_colon' => '',
    'menu_name' => __('Projects')

  );
  $args = array(
    'labels' => $labels,
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true, 
    'show_in_menu' => 'diph-top-level-handle', 
    'query_var' => true,
    'rewrite' => array('slug' => 'projects','with_front' => FALSE),
    'capability_type' => 'page',
    'has_archive' => true, 
    'hierarchical' => true,
    'menu_position' => null,
    'supports' => array( 'title', 'author', 'thumbnail', 'excerpt', 'comments','revisions', 'custom-fields' )
  ); 
  register_post_type('project',$args);
  
}
add_action( 'init', 'diph_project_init' );

//add filter to ensure the text Project, or project, is displayed when user updates a project 
function diph_project_updated_messages( $messages ) {
  global $post, $post_ID;

  $messages['project'] = array(
    0 => '', // Unused. Messages start at index 1.
    1 => sprintf( __('Project updated. <a href="%s">View project</a>'), esc_url( get_permalink($post_ID) ) ),
    2 => __('Custom field updated.'),
    3 => __('Custom field deleted.'),
    4 => __('Project updated.'),
    /* translators: %s: date and time of the revision */
    5 => isset($_GET['revision']) ? sprintf( __('Project restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
    6 => sprintf( __('Project published. <a href="%s">View project</a>'), esc_url( get_permalink($post_ID) ) ),
    7 => __('Project saved.'),
    8 => sprintf( __('Project submitted. <a target="_blank" href="%s">Preview project</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
    9 => sprintf( __('Project scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview project</a>'),
      // translators: Publish box date format, see http://php.net/date
      date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
    10 => sprintf( __('Project draft updated. <a target="_blank" href="%s">Preview project</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
  );

  return $messages;
}
add_filter( 'post_updated_messages', 'diph_project_updated_messages' );


//add custom taxonomies for each project
add_action( 'init', 'create_tax_for_projects', 0 );
function create_tax_for_projects() {
	$args = array( 'post_type' => 'project', 'posts_per_page' => -1 );
	$projects = get_posts($args);
	if ($projects) {
		foreach ( $projects as $project ) {
			$projectTax = 'diph_tax_'.$project->post_name;
			$projectName = $project->post_title;
			$taxonomy_exist = taxonomy_exists($projectTax);
			//returns true
			if(!$taxonomy_exist) {
				diph_create_tax($projectTax,$projectName);
			}
		}
	}
}

function diph_create_tax($taxID,$taxName){

	// Add new taxonomy, make it hierarchical (like categories)
  $labels = array(
    'name' => _x( $taxName, 'taxonomy general name' ),
    'singular_name' => _x( 'Genre', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Genres' ),
    'all_items' => __( 'All Genres' ),
    'parent_item' => __( 'Parent Genre' ),
    'parent_item_colon' => __( 'Parent Genre:' ),
    'edit_item' => __( 'Edit Genre' ), 
    'update_item' => __( 'Update Genre' ),
    'add_new_item' => __( 'Add New Genre' ),
    'new_item_name' => __( 'New Genre Name' ),
    'menu_name' => __( 'Genre' ),
  ); 	

  register_taxonomy($taxID,array('diph-markers'), array(
    'hierarchical' => true,
    'public' => true,
    'labels' => $labels,
    'show_ui' => true,
    'show_in_nav_menus' => false,
    'query_var' => true
  ));
}

function show_tax_on_project_markers() {
	global $post;
	$projectID = get_post_meta($post->ID,'marker_project');
	$project = get_post($projectID[0]);
	$projectTaxSlug = 'diph_tax_'.$project->post_name;
	$diphTaxs = get_taxonomies();
		
	foreach ($diphTaxs as $key => $value) {
		if($value!=$projectTaxSlug) {
			remove_meta_box( $value.'div', 'diph-markers', 'side' );
		}
	}

}

add_action( 'admin_head' , 'show_tax_on_project_markers' );




// Custom scripts to be run on Project new/edit pages only
function add_diph_project_admin_scripts( $hook ) {

    global $post;

    if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
        if ( 'project' === $post->post_type ) {     
			//wp_register_style( 'ol-style', plugins_url('/js/OpenLayers/theme/default/style.css',  dirname(__FILE__) ));
			wp_enqueue_style( 'ol-map', plugins_url('/css/ol-map.css',  dirname(__FILE__) ));
			
			wp_enqueue_style( 'diph-sortable-style', plugins_url('/css/sortable.css',  dirname(__FILE__) ));
			wp_enqueue_style( 'diph-bootstrap-style', plugins_url('/lib/bootstrap/css/bootstrap.min.css',  dirname(__FILE__) ));
			wp_enqueue_style( 'diph-bootstrap-responsive-style', plugins_url('/lib/bootstrap/css/bootstrap-responsive.min.css',  dirname(__FILE__) ));
			wp_enqueue_style( 'diph-style', plugins_url('/css/diph-style.css',  dirname(__FILE__) ));
			
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script(  'diph-jquery-ui', plugins_url('/lib/jquery-ui-1.9.2.custom.min.js', dirname(__FILE__) ));

			wp_enqueue_script(  'diph-bootstrap', plugins_url('/lib/bootstrap/js/bootstrap.min.js', dirname(__FILE__) ),'jquery');
			//wp_enqueue_script(  'bootstrap-button-fix', plugins_url('/lib/bootstrap/js/bootstrap-button-fix.js', dirname(__FILE__) ),'diph-bootstrap');
			
           	
			wp_enqueue_script(  'diph-touch-punch', plugins_url('/lib/jquery.ui.touch-punch.js', dirname(__FILE__) ));
             //wp_enqueue_script(  'open-layers', plugins_url('/js/OpenLayers/OpenLayers.js', dirname(__FILE__) ));
			wp_enqueue_script(  'diph-nested-sortable', plugins_url('/lib/jquery.mjs.nestedSortable.js', dirname(__FILE__) ));
			wp_enqueue_script(  'diph-project-script', plugins_url('/js/diph-project-admin.js', dirname(__FILE__) ));
			wp_enqueue_style('thickbox');
			wp_enqueue_script('thickbox');

        }
    }
	if ( $hook == 'edit.php'  ) {
        if ( 'project' === $post->post_type ) {     
			//wp_register_style( 'ol-style', plugins_url('/js/OpenLayers/theme/default/style.css',  dirname(__FILE__) ));
			wp_enqueue_style( 'ol-map', plugins_url('/css/ol-map.css',  dirname(__FILE__) ));
			wp_enqueue_script(  'jquery' );
             //wp_enqueue_script(  'open-layers', plugins_url('/js/OpenLayers/OpenLayers.js', dirname(__FILE__) ));
			 wp_enqueue_script(  'diph-project-script2', plugins_url('/js/diph-project-admin2.js', dirname(__FILE__) ));
			 
        }
    }
}
add_action( 'admin_enqueue_scripts', 'add_diph_project_admin_scripts', 10, 1 );
if ( function_exists( 'add_theme_support' ) ) {
	add_theme_support( 'post-thumbnails' );
        set_post_thumbnail_size( 32, 37 ); // default Post Thumbnail dimensions
}
// Add the Meta Box
function add_diph_project_settings_box() {
    add_meta_box(
		'diph_settings_box', // $id
		'Project Details', // $title
		'show_diph_project_settings_box', // $callback
		'project', // $page
		'normal', // $context
		'high'); // $priority
}
add_action('add_meta_boxes', 'add_diph_project_settings_box');
// Add the Icon Box
function add_diph_project_icons_box() {
    add_meta_box(
		'diph_icons_box', // $id
		'Marker Icons', // $title
		'show_diph_project_icons_box', // $callback
		'project', // $page
		'side', // $context 
		'default'); // $priority
}
add_action('add_meta_boxes', 'add_diph_project_icons_box');
// Field Array
$prefix = 'project_';
$diph_project_settings_fields = array(
	array(
		'label'=> 'Project Settings',
		'desc'	=> 'Stores the project setup as json.',
		'id'	=> $prefix.'settings',
		'type'	=> 'textarea'
	),array(
		'label'=> 'Icons',
		'desc'	=> 'Icons for categories.',
		'id'	=> $prefix.'icons',
		'type'	=> 'hidden'
	)
);
// The Callback 
function show_diph_project_icons_box() {
	//diph_deploy_icons();
	bdw_get_images();
}
// The Callback
function show_diph_project_settings_box() {

global $diph_project_settings_fields, $post;
// Load post id for project settings
echo '<input type="hidden" id="diph-projectid" value="'.$post->ID.'"/>';
// Use nonce for verification
echo '<input type="hidden" name="diph_project_settings_box_nonce" value="'.wp_create_nonce(basename(__FILE__)).'" />';
	//echo '<div id="map-divs"></div><button id="locate">Locate me!</button>';

	// Begin the field table and loop
	echo '<table class="project-form-table">';
	foreach ($diph_project_settings_fields as $field) {
		// get value of this field if it exists for this post
		$meta = get_post_meta($post->ID, $field['id'], true);
		// begin a table row with
		
				switch($field['type']) {
					// case items will go here
					// text
					case 'text':
						echo '<tr>
							<th><label for="'.$field['id'].'">'.$field['label'].'</label></th>
							<td>';
						echo '<input type="text" name="'.$field['id'].'" id="'.$field['id'].'" value="'.$meta.'" size="30" />
							<br /><span class="description">'.$field['desc'].'</span>';
							echo '</td></tr>';
					break;
					// textarea
					case 'textarea':
						echo '<tr>
							<th><label for="'.$field['id'].'">'.$field['label'].'</label></th>
							<td>';
						echo '<textarea name="'.$field['id'].'" id="'.$field['id'].'" cols="60" rows="4">'.$meta.'</textarea>
							<br /><span class="description">'.$field['desc'].'</span>';
							echo '</td></tr>';
					break;
					// checkbox
					case 'checkbox':
						echo '<tr>
							<th><label for="'.$field['id'].'">'.$field['label'].'</label></th>
							<td>';
						echo '<input type="checkbox" name="'.$field['id'].'" id="'.$field['id'].'" ',$meta ? ' checked="checked"' : '','/>
							<label for="'.$field['id'].'">'.$field['desc'].'</label>';
							echo '</td></tr>';
					break;
					// select
					case 'select':
						echo '<tr>
							<th><label for="'.$field['id'].'">'.$field['label'].'</label></th>
							<td>';
						echo '<select name="'.$field['id'].'" id="'.$field['id'].'">';
						foreach ($field['options'] as $option) {
							echo '<option', $meta == $option['value'] ? ' selected="selected"' : '', ' value="'.$option['value'].'">'.$option['label'].'</option>';
						}
						echo '</select><br /><span class="description">'.$field['desc'].'</span>';
						echo '</td></tr>';
					break;
					// textarea
					case 'hidden':
					
						echo '<input type="hidden" name="'.$field['id'].'" id="'.$field['id'].'" value="'.$meta.'" />';
		
					break;
				} //end switch
		
	} // end foreach
	echo '</table>'; // end table	
	print_new_bootstrap_html();
}

// Save the Data
function save_diph_project_settings($post_id) {
    global $diph_project_settings_fields;
	$parent_id = wp_is_post_revision( $post_id );
	
	// verify nonce
	if (!wp_verify_nonce($_POST['diph_project_settings_box_nonce'], basename(__FILE__)))
		return $post_id;
	// check autosave
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		return $post_id;
	// check permissions
	if ('page' == $_POST['post_type']) {
		if (!current_user_can('edit_page', $post_id))
			return $post_id;
		} elseif (!current_user_can('edit_post', $post_id)) {
			return $post_id;
	}
	if ( $parent_id ) {
		// loop through fields and save the data
		$parent  = get_post( $parent_id );
		
		foreach ($diph_project_settings_fields as $field) {
			$old = get_post_meta( $parent->ID, $field['id'], true);
			$new = $_POST[$field['id']];
			if ($new && $new != $old) {
				update_metadata( 'post', $post_id, $field['id'], $new);
			} elseif ('' == $new && $old) {
				delete_metadata( 'post', $post_id, $field['id'], $old);
			}
		} // end foreach
	}
	else {
		// loop through fields and save the data
		foreach ($diph_project_settings_fields as $field) {
			$old = get_post_meta($post_id, $field['id'], true);
			$new = $_POST[$field['id']];
			if ($new && $new != $old) {
				update_post_meta($post_id, $field['id'], $new);
			} elseif ('' == $new && $old) {
				delete_post_meta($post_id, $field['id'], $old);
			}
		} // end foreach
	}
}
add_action('save_post', 'save_diph_project_settings');  

//create arrays of custom field values associated with a project
function createMoteValueArrays($mote_name,$project_id){

	//loop through all markers in project -add to array
	$moteArray = array();
	$projectObj = get_post($project_id);
	$diph_tax_name = 'diph_tax_'.$projectObj->post_name;


	$args = array( 'post_type' => 'diph-markers', 'meta_key' => 'marker_project','meta_value'=>$project_id, 'posts_per_page' => -1 );
	$loop = new WP_Query( $args );
	while ( $loop->have_posts() ) : $loop->the_post();

		$marker_id = get_the_ID();
		$temp_post = get_post($marker_id);
		$tempMoteValue = get_post_meta($marker_id,$mote_name);
		$tempMoteArray = split(';',$tempMoteValue[0]);

		//array_push($moteArray,$tempMoteValue[0]); 
		//array_push($moteArray,$tempMoteArray.length); 
		$termsArray = array();
		for($i=0;$i<count($tempMoteArray);$i++) {
			$term_name = trim($tempMoteArray[$i]);

			if(term_exists( $term_name, $diph_tax_name )) {
				$ttermid = get_term_by('name', $term_name, $diph_tax_name);
				array_push($termsArray, $ttermid->term_id);
			}
			array_push($moteArray, $term_name);
		}
		wp_set_post_terms( $marker_id, $termsArray, &$diph_tax_name );
		
	endwhile;

	 //$result = array_unique($array)
	return $moteArray;
}

//create unique array of custom fields of posts associated with the project
function createUniqueCustomFieldArray($project_id){

	//loop through all markers in project -add to array
	$custom_field_array = array();

	$args = array( 'post_type' => 'diph-markers', 'meta_key' => 'marker_project','meta_value'=>$project_id, 'posts_per_page' => -1 );
	$loop = new WP_Query( $args );
	while ( $loop->have_posts() ) : $loop->the_post();

		$marker_id = get_the_ID();
		$temp_custom_field_keys = get_post_custom_keys($marker_id);

		foreach($temp_custom_field_keys as $key => $value) {
			$valuet = trim($value);
     		if ( '_' == $valuet{0} )
      		continue;
			array_push($custom_field_array, $value);
		}
				
	endwhile;

	$unique_custom_fields = array_unique($custom_field_array);

	return $unique_custom_fields;
}


function invertLatLon($latlon){
	if($latlon) {
	$tempLonLat = split(',',$latlon);
	
	return $tempLonLat[1].','.$tempLonLat[0];
	}
}
function getIconsForTerms($parent_terms, $taxonomy){
	
	$json_filter = '{ "type":"filter", "terms" :[';
	
	foreach ( $parent_terms as $term ) {
			if($i>0) {
				$json_filter .= ',';
			}
			else {$i++;}
			$children_terms = get_term_children( $term->term_id, $taxonomy );
			$children_names = array();
			foreach ($children_terms as $child) {
				$child_name = get_term_by('id', $child, $taxonomy);
				array_push($children_names, $child_name->name);
			}
			$icon_url = get_term_meta($term->term_id,'icon_url',true);
			$json_filter .= '{ "name":"'.$term->name.'","icon_url" :"'.$icon_url.'", "children" : '.json_encode($children_names).'}';

	}
	$json_filter .= ']}';
	return $json_filter;
}
function createMarkerArray($project_id) {
	//loop through all markers in project -add to array
	$markerArray = array();
	$project_object = get_post($project_id);
	$project_tax = 'diph_tax_'.$project_object->post_name;

	//LOAD PROJECT SETTINGS
	//-get primary category parent

	$parent = get_term_by('name', "Primary Concept", $project_tax);
	//$parent = get_terms($project_tax, array('parent=0&hierarchical=0&number=1'));
	//print_r($parent);
	$parent_term_id = $parent->term_id;
	$parent_terms = get_terms( $project_tax, array( 'parent' => $parent_term_id, 'orderby' => 'term_group' ) );

	$term_icons = getIconsForTerms($parent_terms, $project_tax);

	$json_string = '['.$term_icons.',{"type": "FeatureCollection","features": [';
	$args = array( 'post_type' => 'diph-markers', 'meta_key' => 'marker_project','meta_value'=>$project_id, 'posts_per_page' => -1 );
	$loop = new WP_Query( $args );
	$i = 0;
	while ( $loop->have_posts() ) : $loop->the_post();

		$marker_id = get_the_ID();
		$tempMarkerValue = get_post_meta($marker_id,$mote_name);
		$tempMoteArray = split(';',$tempMoteValue[0]);
		$latlon = get_post_meta($marker_id,'Location0');
		$lonlat = invertLatLon($latlon[0]);
		$title = get_the_title();
		$categories = get_post_meta($marker_id,'Concepts');
		$args = array("fields" => "names");
		$post_terms = wp_get_post_terms( $marker_id, $project_tax, $args );
		$p_terms;
		foreach ($post_terms as $term ) {
			$p_terms .= $term.',';
		}
		

		if($lonlat) {
			if($i>0) {
				$json_string .= ',';
			}
			else {$i++;}
		$json_string .= '{"type": "Feature","geometry": { "type": "Point","coordinates": [ '.$lonlat. '] },';
		$json_string .= '"properties": {"title": "'.$title.'","categories": '.json_encode($post_terms).'}}';

		//array_push($markerArray,$json_string); 
		}
	
		
	endwhile;
$json_string .= ']}]';	
	 //$result = array_unique($array)
	return $json_string;
}
function dateFormatSplit($date_range){
	$posA = strpos($date_range, "~");
	$posE = strpos($date_range, "-");
	if($posE > 0) {
    	$dateArray = explode('-', $date_range);	
    	if($dateArray[1]=='') {
    		$dateArray[1] = $dateArray[0];
    	}
	}
	if($posA > 0) {
    	$dateArray = explode('~', $date_range);	
    	if($dateArray[1]=='') {
    		$dateArray[1] = $dateArray[0];
    	}
	}
	return $dateArray;
}
function createTimelineArray($project_id) {
	//loop through all markers in project -add to array
	$timelineArray = array();
	$project_object = get_post($project_id);
	$project_tax = 'diph_tax_'.$project_object->post_name;

	//LOAD PROJECT SETTINGS
	//-get primary category parent

	$parent = get_term_by('name', "Primary Concept", $project_tax);
	//$parent = get_terms($project_tax, array('parent=0&hierarchical=0&number=1'));
	//print_r($parent);
	$parent_term_id = $parent->term_id;
	$parent_terms = get_terms( $project_tax, array( 'parent' => $parent_term_id, 'orderby' => 'term_group' ) );

	$term_icons = getIconsForTerms($parent_terms, $project_tax);

	$json_string = '{"timeline":{"headline":"Long Womens Movement","type":"default","text":"A journey","date":[';
	$args = array( 'post_type' => 'diph-markers', 'meta_key' => 'marker_project','meta_value'=>$project_id, 'posts_per_page' => -1 );
	$loop = new WP_Query( $args );
	$i = 0;
	while ( $loop->have_posts() ) : $loop->the_post();

		$marker_id = get_the_ID();
		$tempMarkerValue = get_post_meta($marker_id,$mote_name);
		$tempMoteArray = split(';',$tempMoteValue[0]);
		$latlon = get_post_meta($marker_id,'Location0');

		$lonlat = invertLatLon($latlon[0]);
		$title = get_the_title();
		$categories = get_post_meta($marker_id,'Primary Concept');
		$date = get_post_meta($marker_id,'date_range');
		$dateA = dateFormatSplit($date[0]);

		$startDate = $dateA[0];
		$endDate = $dateA[1];
		$args = array("fields" => "names");
		$post_terms = wp_get_post_terms( $marker_id, $project_tax, $args );
		$p_terms;
		foreach ($post_terms as $term ) {
			$p_terms .= $term.',';
		}
		if($i>0) {
			$json_string .= ',';
		}
		else {$i++;}
			
		$json_string .= '{"startDate":"'.$startDate.'","endDate":"'.$endDate.'","headline":"'.$title.'","text":"'.$categories[0].'","asset":{"media":"","credit":"","caption":""}}';

		//array_push($markerArray,$json_string); 
		
		
	endwhile;
$json_string .= ']}}';	
	 //$result = array_unique($array)
	return $json_string;
}
//used in print_new_bootstrap_html()
function create_custom_field_option_list($cf_array){
	
	foreach ($cf_array as $key => &$value) {
		$optionHtml .='<option value="'.$value.'">'.$value.'</option>';
	}
	return $optionHtml;
}

function print_new_bootstrap_html(){
	echo '<div class="new-bootstrap">
    <div class="row-fluid"> 	
    	<div class="span12">
        <div class="tabbable tabs-left">
          <ul class="nav nav-tabs">
           <li class="active"><a href="#entry-point" data-toggle="tab">Entry Points</a></li>
           <li><a href="#motes" data-toggle="tab">Motes</a></li>
           <li><a href="#shared" data-toggle="tab">Shared</a></li>
           <li><a href="#views" data-toggle="tab">Views</a></li>
            <a id="save-btn" type="button" class="btn btn-danger" data-loading-text="Saving...">Save</a>
          </ul>
          <div class="tab-content">
            <div id="entry-point" class="tab-pane fade in active">
              <h4>Entry Points</h4>
              <ul id="entryTabs" class="nav nav-tabs">
                <li class="active"><a href="#home" data-toggle="tab">Home</a></li>           
                <li class="dropdown  pull-right">
                  <a href="#" class="dropdown-toggle" data-toggle="dropdown">Create Entry Point<b class="caret"></b></a>
                  <ul class="dropdown-menu">
                    <li><a id="add-map" >Map</a></li>
                    <li><a id="add-timeline" >Timeline</a></li>
                    <li class="disabled"><a >Topic Cards</a></li>
                    <li class="disabled"><a >A/V Transcript</a></li>
                  </ul>
                </li>
              </ul>

              <div id="entryTabContent" class="tab-content">
                <div class="tab-pane fade in active" id="home">
                  <p>Create entry points to the project using the right most tab above. </p>
                </div>               
              </div>
            </div>

            <div id="motes" class="tab-pane fade in">
              <h4>Motes</h4>
              <p>Create relational containers for the data in the custom fields</p>
              <div id="create-mote">
                <p><a class="btn btn-success" id="create-btn">Create mote</a></p>
                <p>
                  <input class="span4 mote-name" type="text" name="mote-name" placeholder="Mote Name" />
                </p>
                <p>
                  <select name="custom-fields" class="custom-fields">'.create_custom_field_option_list(createUniqueCustomFieldArray($post->ID)).'
                  </select><span class="help-inline">Choose a data object (custom field)</span>
                  <label class="checkbox inline">
                    <input type="checkbox" id="pickMultiple" value="multiple"> Multiple
                  </label>
                </p>
                <p>
                  <select name="cf-type" class="cf-type">                          
                    <option>Text</option>
                    <option>Multiple Text</option>
                    <option>HTML</option>
                    <option>Exact Date</option>
                    <option>Date Range</option>
                    <option>Lat/Lon Coordinates</option>
                    <option>File</option>
                  </select><span class="help-inline">Choose a data type</span>
                </p>
                <p><input class="delim" type="text" name="delim" placeholder="Delimiter" /> If multiple text indicate the delimiter</p>
              </div>
              
              <div class="accordion" id="mote-list">                
              </div>              
            </div>
            <div id="shared" class="tab-pane fade in">
              <h4>Shared resources</h4>
              <p>Create motes to use that are stored in the project and not markers</p>
              <p>ex. Transcripts that are shared across markers. Saved in the project custom field. </p>

            </div>
            <div id="views" class="tab-pane fade in">
              <h4>Views</h4>
              Create and arrange a layout for the entry points
              <div class="btn-group" data-toggle="buttons-radio">
                <button type="button" class="btn btn-primary">Left</button>
                <button type="button" class="btn btn-primary">Middle</button>
                <button type="button" class="btn btn-primary">Right</button>
              </div>
            </div>
          </div>
        </div>
      </div>  
    </div>
</div>
<!-- Modal -->
      <div id="myModal" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    <h3 id="myModalLabel">Map Setup</h3>
  </div>
  <div class="modal-body">
    <p>Zoom and drag to set your map\'s initial position.</p>
  </div>
  <div class="modal-footer">
    <button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
    <button class="btn btn-primary">Save changes</button>
  </div>
</div>';
}
function categoryString($cats){
	$catString;

	foreach ($catArray as $key => $cat) {
		if($key>0) {
			$catString .= ','.$cat;
		}
		else {
			$catString .= $cat;
		} 
	}
	return $catString;
}
function createParentTerm($term_name,$diph_tax_name){

	if(term_exists( $term_name, $diph_tax_name )) {
			$temp_term = get_term_by('name', $term_name, $diph_tax_name);
			$term_id = $temp_term->id;			
			wp_update_term( $term_id, $diph_tax_name );
	}
	else {
		wp_insert_term( $term_name, $diph_tax_name );
	}

}
//ajax functions
function diphSaveProjectSettings(){
	$settings =  $_POST['settings'];
	$diph_projectID = $_POST['project'];

	update_post_meta($diph_projectID, 'project_settings', $settings);

	die('working... '. $settings);
}
add_action( 'wp_ajax_diphSaveProjectSettings', 'diphSaveProjectSettings' );
function diphGetMoteValues(){
	$mote_values = array();
	$mote_name = $_POST['mote_name'];
	$diph_projectID = $_POST['project'];

	$diph_project = get_post($diph_projectID);
	$diph_project_slug = $diph_project->post_name;
	$diph_tax_name = 'diph_tax_'.$diph_project_slug;
	createParentTerm($mote_name,$diph_tax_name);
	//get fresh terms from meta feild 
	$mArray = createMoteValueArrays($mote_name,$diph_projectID);

	$term_counts = array_count_values($mArray);	

	$parent_term = get_term_by('name', $mote_name, $diph_tax_name);
	$parent_id = $parent_term->term_id;
	$args = array('parent' => $parent_id);
	//loop through terms and add to taxonomy
	foreach ($term_counts as $key=>$term) {
		$term_name = $key;
		//$args = array();		

		$term_id = term_exists( $term_name, $diph_tax_name );

		if($term_id) {	
			$old_parent = $term_id->parent;
			
			//wp_update_term( $term_id, $diph_tax_name );
			//clean_term_cache($parent_term->term_id, $diph_tax_name,true,true);
		}
		else {			
			wp_insert_term( $term_name, $diph_tax_name, $args );
			//clean_term_cache($parent_term->term_id, $diph_tax_name,true,true);
		}
		
	}	
	//clean_term_cache($parent_term->term_id, $diph_tax_name,true,true);
	delete_option("{$diph_tax_name}_children");
	$terms_loaded = get_terms($diph_tax_name, 'orderby=term_group&hide_empty=0');
	$terms = get_terms($diph_tax_name, array( 'orderby' => 'term_id' ) );
 	$t_count = count($terms_loaded);

 	//return wp tax id,name,count,order
 	//$diph_top_term = get_term_by('name', $term_name, $diph_tax_name);
 	if ( $t_count > 0 ){
   		foreach ( $terms_loaded as $term ) {
  	    	

  	    	$term_url = get_term_meta($term->term_id, 'icon_url', true);
			//$term .= '"icon_url" : "'.$term_url.'"';

			$term ->icon_url = $term_url;
  	    	//array_push($term, array('icon_url' => $term_url ));
		}
	}

	die(json_encode($terms_loaded));
}
add_action( 'wp_ajax_diphGetMoteValues', 'diphGetMoteValues' );

function diphGetMarkers(){

	$diph_project = $_POST['project'];
	$mArray = createMarkerArray($diph_project);
	
	die(json_encode($mArray));
}
//show on both front and backend
add_action( 'wp_ajax_diphGetMarkers', 'diphGetMarkers' );
add_action('wp_ajax_nopriv_diphGetMarkers', 'diphGetMarkers');

function diphGetTimeline(){

	$diph_project = $_POST['project'];
	$mArray = createTimelineArray($diph_project);
	
	die(json_encode($mArray));
}
//show on both front and backend
add_action( 'wp_ajax_diphGetTimeline', 'diphGetTimeline' );
add_action('wp_ajax_nopriv_diphGetTimeline', 'diphGetTimeline');

function diphCreateTaxTerms(){
	$mote_parent = $_POST['mote_name'];
	$diph_projectID = $_POST['project'];
	$diph_project_terms = str_replace('\\', '', $_POST['terms']);
	$diph_project_terms = json_decode($diph_project_terms);
	
	$diph_project = get_post($diph_projectID);
	$diph_project_slug = $diph_project->post_name;
	$diph_tax_name = 'diph_tax_'.$diph_project_slug;

	$mote_parent_id = term_exists( $mote_parent, $diph_tax_name );
	$meta_key = 'icon_url';
	

	$testArray = array();
	array_push($testArray, 'here');
	foreach ($diph_project_terms as $term) {
		$term_name = $term->{'name'};
		$parent_term_id = $term->{'parent'};
		$term_id = $term->{'term_id'};	
		$term_order = $term->{'term_order'};	
		$meta_value = $term->{'icon_url'};

		if($meta_value=='undefined') { $meta_value = '';}
		
		if( ($parent_term_id==0||$parent_term_id==""||$parent_term_id==null) && ($term_id!=$mote_parent) ) {
			$parent_term_id = $mote_parent;
		}

		$args = array( 'parent' => $parent_term_id,'term_group' =>  $term_order );

		if(term_exists( $term_name, $diph_tax_name )) {
					
			wp_update_term( $term_id, $diph_tax_name, $args );
			update_term_meta($term_id, $meta_key, $meta_value);
		}
		else {
			wp_insert_term( $term_name, $diph_tax_name, $args );
			update_term_meta($term_id, $meta_key, $meta_value);
		}
		array_push($testArray, $meta_value);
	}
	
	die(json_encode($testArray));
}
add_action( 'wp_ajax_diphCreateTaxTerms', 'diphCreateTaxTerms' );




// Restore revision
function diph_project_restore_revision( $post_id, $revision_id ) {
	global $diph_project_settings_fields;
	$post     = get_post( $post_id );
	$revision = get_post( $revision_id );
	foreach ($diph_project_settings_fields as $field) {
			$old = get_metadata( 'post', $revision->ID, $field['id'], true);
			if ( false !== $old) {
				update_post_meta($post_id, $field['id'], $old);
			} else {
				delete_post_meta($post_id, $field['id'] );
			}
		} // end foreach
}
add_action( 'wp_restore_post_revision', 'diph_project_restore_revision', 10, 2 );


function my_plugin_revision_fields( $fields ) {

	$fields['my_meta'] = 'My Meta';
	return $fields;

}
//add_filter( '_wp_post_revision_fields', 'my_plugin_revision_fields' );

function my_plugin_revision_field( $value, $field ) {

	global $revision;
	return get_metadata( 'post', $revision->ID, $field, true );

}
//add_filter( '_wp_post_revision_field_my_meta', 'my_plugin_revision_field', 10, 2 );

// Set template to be used for Project type
function diph_page_template( $page_template )
{
	
	$post_type = get_query_var('post_type');
    if ( $post_type == 'project' ) {
        $page_template = dirname( __FILE__ ) . '/diph-project-template.php';
        wp_enqueue_style( 'ol-map', plugins_url('/css/ol-map.css',  dirname(__FILE__) ));
			
		wp_enqueue_script('jquery');
		wp_enqueue_script('backbone');
		wp_enqueue_script('underscore');
		//wp_enqueue_script( 'open-layers', 'http://dev.openlayers.org/releases/OpenLayers-2.12/lib/OpenLayers.js' );
    	wp_enqueue_script( 'open-layers', plugins_url('/js/OpenLayers/OpenLayers.js', dirname(__FILE__) ));
        wp_enqueue_script( 'timeline-js', plugins_url('/js/storyjs-embed.js', dirname(__FILE__) ));
     
        wp_enqueue_script( 'diph-public-project-script', plugins_url('/js/diph-project-page.js', dirname(__FILE__) ));
		wp_enqueue_style('thickbox');
		wp_enqueue_script('thickbox');
    }
    return $page_template;
}
add_filter( 'single_template', 'diph_page_template' );


/**
 * Deploy the icons list to select one
 */
function diph_deploy_icons(){ 
	
	
	$icon_path = DIPH_PLUGIN_URL.'/images/icons/';
	$icon_dir = DIPH_PLUGIN_DIR.'/images/icons/';	
	
	$icons_array = array();
	
	
	if ($handle = opendir($icon_dir)) {
		
		while (false !== ($file = readdir($handle))) {
	
			$file_type = wp_check_filetype($file);
	
			$file_ext = $file_type['ext'];
		
			if ($file != "." && $file != ".." && ($file_ext == 'gif' || $file_ext == 'jpg' || $file_ext == 'png') ) {
				array_push($icons_array,$icon_path.$file);
			}
		}
	}
	?>
          	   
		<div id="diph_icon_cont">
        	
		<?php $i = 1; foreach ($icons_array as $icon){ ?>
		  <div class="diph_icon" id="icon_<?php echo $i;?>">
		  <img src="<?php echo $icon; $i++; ?>" /> 
		  </div>
		<?php } ?>
        
		 </div> 
         <div id="icon-cats"><ul>
         
         </ul></div>
         
         	
	<?php
}
function bdw_get_images() {
 global $post;
    // Get the post ID
    $iPostID = $post->ID;
 
    // Get images for this post
    $arrImages =& get_children('post_type=attachment&post_mime_type=image&numberpost=-1&post_parent=' . $iPostID );
 
    // If images exist for this page
    if($arrImages) {
 
        // Get array keys representing attached image numbers
        $arrKeys = array_keys($arrImages);

 
        $sImgString .= '<div class="misc-pub-section icons">';
 
        // UNCOMMENT THIS IF YOU WANT THE FULL SIZE IMAGE INSTEAD OF THE THUMBNAIL
        //$sImageUrl = wp_get_attachment_url($iNum);
        $i = 0;
 		foreach ($arrKeys as $field) {
 			// Get the first image attachment
        	$iNum = $arrKeys[$i];
 			$i++;
        	// Get the thumbnail url for the attachment
       		$sThumbUrl = wp_get_attachment_thumb_url($iNum);
        	// Build the <img> string
        	$sImgString .= '<a id="'.$iNum.'" >' .
                            '<img src="' . $sThumbUrl . '"/>' .
                        '</a>';
 		}
 		$sImgString .= '</div>';
        // Print the image
        echo $sImgString;
    }
}