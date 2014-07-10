// DH Press Pinboard View
// ASSUMES: Visualization area is marked with HTML div as "dhp-visual"
// NOTES:   Format of Marker and Legend data (GeoJSON) is documented in dhp-project-functions.php
//          The class active-legend is added to whichever legend is currently visible and selected

// USES:    JavaScript libraries jQuery, Underscore, Snap svg


var dhpPinboardView = {

        // Contains fields: ajaxURL, projectID, mapEP, viewParams, vizIndex
        //                  callBacks = object containing callback functions to dhp-project-page

        //					rawAjaxData = raw data returned from AJAX
        //					allMarkers = All marker posts assoc. w/ Project; see data desc in createMarkerArray() of dhp-project-functions.php
        //                          Points to features array

        //                  filters = array of all reformatted(flattend) Legend/Filter data
        //                  curLgdName = name of current legend/filter
        //                  curLgdFilter = pointer to current legend/filter array

        //                  radius = radius of geometric markers
        //                  iconSize = "s" | "m" | "l"

        //                  viewL, viewT, viewW, viewH = current viewport into background image
        //                  viewScale = current zoom scale % (100=fullsize)
        //                  zoomStep = % of zoom or reduce for each step

        //                  useParent = if true (always true!), actions on parent term affect child terms
        //                  isTouch = this is a touch-screen interface, not mouse
        //                  currentFeature = map feature currently highlighted or selected (with modal)
        //                  anyPopupsOpen = true when a popover modal is currently open

        // PURPOSE: Initialize new leaflet map, layers, and markers                         
        // INPUT:   ajaxURL      = URL to WP
        //          projectID    = ID of project
        //          mapEP        = settings for map entry point (from project settings)
        //          viewParams   = array of data about map layers (see dhpGetMapLayerData() in dhp-project-functions)
        //          callBacks    = set of callback functions back to dhp-project-page functions
    initialize: function(ajaxURL, projectID, vizIndex, pinboardEP, viewParams, callBacks) {
             // Constants
        dhpPinboardView.checkboxHeight  = 12; // default checkbox height
        dhpPinboardView.minWidth        = 182; // 182px for horizontal + 260px for Legend key
        dhpPinboardView.controlHeight   = 49;  // max(navButtonHeight[30], LegendHeight[45]) + 4

            // Save visualization data for later
        dhpPinboardView.ajaxURL        = ajaxURL;
        dhpPinboardView.projectID      = projectID;
        dhpPinboardView.vizIndex       = vizIndex;
        dhpPinboardView.pinboardEP     = pinboardEP;
        dhpPinboardView.viewParams     = viewParams;
        dhpPinboardView.callBacks      = callBacks;

        dhpPinboardView.isTouch        = dhpPinboardView.isTouchDevice();

        dhpPinboardView.iconSize       = pinboardEP.size;
        switch (pinboardEP.size) {
        case "s":
            dhpPinboardView.radius     = 4;
            break;
        case "m":
            dhpPinboardView.radius     = 8;
            break;
        case "l":
            dhpPinboardView.radius     = 12;
            break;
        }
            // Expand to show/hide child terms and use their colors
        dhpPinboardView.useParent = true;

        dhpPinboardView.anyPopupsOpen = false;

            // set view/scroll window parameters

            // viewBox coordinates -- start out 1-1 actual pixel view
        dhpPinboardView.viewL=0;
        dhpPinboardView.viewT=0;
        dhpPinboardView.viewW=pinboardEP.width;
        dhpPinboardView.viewH=pinboardEP.height;
        dhpPinboardView.viewScale=100;
        dhpPinboardView.zoomStep=10;

            // Add pinboard elements to nav bar
        jQuery('.dhp-nav .top-bar-section .left').append(Handlebars.compile(jQuery("#dhp-script-pin-menus").html()));

            // Set total size of visualization space to background image plus navigation controls
        jQuery("#dhp-visual").width(pinboardEP.width < dhpPinboardView.minWidth ?
                                    dhpPinboardView.minWidth : pinboardEP.width+4);
        jQuery("#dhp-visual").height(pinboardEP.height+dhpPinboardView.controlHeight);

            // Create control div for Legend and image navigation buttons
        jQuery("#dhp-visual").append('<div id="dhp-controls"></div>');

            // Create placeholder for Legend menu
        jQuery('#dhp-controls').append(Handlebars.compile(jQuery("#dhp-script-pin-legend-head").html()));

            // Create buttons for navigating & zooming background image
        jQuery('#dhp-controls').append('<div id="dhp-image-controls"><ul><li class="refresh"></li><li class="zoom"></li><li class="reduce"></li><li class="left"></li><li class="right"></li><li class="up"></li><li class="down"></li></ul></div>');
        jQuery("#dhp-image-controls .refresh").click(dhpPinboardView.resetView);
        jQuery("#dhp-image-controls .zoom").click(dhpPinboardView.zoomIn);
        jQuery("#dhp-image-controls .reduce").click(dhpPinboardView.zoomOut);
        jQuery("#dhp-image-controls .left").click(dhpPinboardView.goLeft);
        jQuery("#dhp-image-controls .right").click(dhpPinboardView.goRight);
        jQuery("#dhp-image-controls .up").click(dhpPinboardView.goUp);
        jQuery("#dhp-image-controls .down").click(dhpPinboardView.goDown);

            // Initialize Snap and create "paper" palette
        dhpPinboardView.svgRoot = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        jQuery(dhpPinboardView.svgRoot).width(pinboardEP.width+2).height(pinboardEP.height+2);
        // jQuery(dhpPinboardView.svgRoot).css({"border": "1px solid red" });

        jQuery("#dhp-visual").append(dhpPinboardView.svgRoot);
        dhpPinboardView.paper = Snap(dhpPinboardView.svgRoot);

            // Create background image
        dhpPinboardView.paper.image(pinboardEP.imageURL, 0, 0, pinboardEP.width, pinboardEP.height);

        dhpPinboardView.loadMarkers();
    }, // initPinboard()


        // PURPOSE: Reset background image to initial 1-1 setting
    resetView: function() {
        dhpPinboardView.viewL=0;
        dhpPinboardView.viewT=0;
        dhpPinboardView.viewW=dhpPinboardView.pinboardEP.width;
        dhpPinboardView.viewH=dhpPinboardView.pinboardEP.height;
        viewScale=100;
        dhpPinboardView.recalcViewBox();
    }, // resetView()


    zoomIn: function() {
        var centX, centY, newW, newH;
            // Allow a maximum of 5x zoom
        if (dhpPinboardView.viewScale > 20) {
                // To zoom in, we need to substract % of pix shown!
            dhpPinboardView.viewScale -= dhpPinboardView.zoomStep;
                // Compute current width, height and center point
            centX = dhpPinboardView.viewL + (dhpPinboardView.viewW / 2);
            centY = dhpPinboardView.viewT + (dhpPinboardView.viewH / 2);
                // calculate new width and height according to new zoom
            newW = ((dhpPinboardView.pinboardEP.width * dhpPinboardView.viewScale) / 100);
            newH = ((dhpPinboardView.pinboardEP.height * dhpPinboardView.viewScale) / 100);
                // calculate new viewBox coords
            dhpPinboardView.viewL = centX - (newW / 2);
            dhpPinboardView.viewW = newW;
            dhpPinboardView.viewT = centY - (newH / 2);
            dhpPinboardView.viewH = newH;
            dhpPinboardView.recalcViewBox();
        }
    }, // zoomIn()


    zoomOut: function() {
        var centX, centY, newW, newH;
            // Allow a maximum of 1.5x zoom
        if (dhpPinboardView.viewScale < 150) {
                // To zoom out, we need to add % of pix shown!
            dhpPinboardView.viewScale += dhpPinboardView.zoomStep;
                // Compute current width, height and center point
            centX = dhpPinboardView.viewL + (dhpPinboardView.viewW / 2);
            centY = dhpPinboardView.viewT + (dhpPinboardView.viewH / 2);
                // calculate new width and height according to new zoom
            newW = ((dhpPinboardView.pinboardEP.width * dhpPinboardView.viewScale) / 100);
            newH = ((dhpPinboardView.pinboardEP.height * dhpPinboardView.viewScale) / 100);
                // calculate new viewBox coords
            dhpPinboardView.viewL = centX - (newW / 2);
            dhpPinboardView.viewW = newW;
            dhpPinboardView.viewT = centY - (newH / 2);
            dhpPinboardView.viewH = newH;
            dhpPinboardView.recalcViewBox();
        }
    }, // zoomOut()


    goLeft: function() {
        var stepX;
        stepX = (dhpPinboardView.pinboardEP.width * dhpPinboardView.zoomScale) / 100;
            // calculate new viewBox coords
        dhpPinboardView.viewL += stepX;
        dhpPinboardView.recalcViewBox();
    }, // goLeft()


    goRight: function() {
        var stepX;
        stepX = (dhpPinboardView.pinboardEP.width * dhpPinboardView.zoomScale) / 100;
            // calculate new viewBox coords
        dhpPinboardView.viewL -= stepX;
        recalcViewBox();
    }, // goRight()


    goUp: function() {
        var stepY;
        stepY = (dhpPinboardView.pinboardEP.height * dhpPinboardView.zoomScale) / 100;
            // calculate new viewBox coords
        dhpPinboardView.viewT += stepY;
        dhpPinboardView.recalcViewBox();
    }, // goUp()


    goDown: function() {
        var stepY;
        stepY = (dhpPinboardView.pinboardEP.height * dhpPinboardView.zoomScale) / 100;
            // calculate new viewBox coords
        dhpPinboardView.viewT -= stepY;
        dhpPinboardView.recalcViewBox();
    }, // goDown()


        // PURPOSE: Set new viewBox given current coordinates
    recalcViewBox: function() {
            // reset viewBox with new settings (top, left, width, height)
        var newSettings = dhpPinboardView.viewL.toString()+" "+dhpPinboardView.viewT.toString()+" "+
                            dhpPinboardView.viewW.toString()+" "+dhpPinboardView.viewH.toString();
        // console.log("resetting viewbox to "+newSettings);
        dhpPinboardView.svgRoot.setAttribute("viewBox", newSettings);
    }, // recalcViewBox()


        // PURPOSE: Create marker objects for pinboard visualization; called by loadMapMarkers()
        // INPUT:   geoData = all AJAX data as JSON object: Array of ["type", ...]
        // SIDE-FX: assigns variables rawAjaxData, allMarkers, filters
    createDataObjects: function(geoData) 
    {
        dhpPinboardView.rawAjaxData = geoData;

        dhpPinboardView.filters = [];

            // Assign data to appropriate objects
        _.each(dhpPinboardView.rawAjaxData, function(dataSet) {
            switch(dataSet.type) {
            case 'filter':
                dhpPinboardView.filters.push(dhpPinboardView.reformatTerms(dataSet));
                break;
            case 'FeatureCollection':
                dhpPinboardView.allMarkers = dataSet.features;
                break;
            }
        });

            // filters will now consist of array of objects with .name and .terms properties
            // each .terms property is an array of flattened legend key arrays

            // First legend will be selected by default
        dhpPinboardView.createLegends();
        dhpPinboardView.createSVG();
    }, // createDataObjects()


        // RETURNS: An array of indices to the markers belonging to the given category
    getMarkersOfCategory: function(theCategory)
    {
        var catIndices=[], found;

            // Go through all of the markers
        _.each(dhpPinboardView.allMarkers, function(theMarker, index) {
                // For each marker, see if it is marked with this category
            found= _.find(theMarker.properties.categories, function(theCat) {
                return theCat == theCategory;
            });
            if (found) {
                catIndices.push(index);
            }
        });
        return catIndices;
    }, // getMarkersOfCategory()


        // PURPOSE: Create SVG data to represent markers on image
        // INPUT:   Array of Legend/Filters
        // ASSUMES: 2nd-level Legend value gets checked or unchecked by 1st-level parent, so that
        //              Markers can retain their distinctive 2nd-level IDs
        // NOTES:   Need to create nested <g> groups to correspond to Legend heads and values
        //          Then we can show or hide various Legends by setting attributes
        //          Legend heads will have class .lgd-head and id #lgd-id+ID#
        //          Legend values will have class .lgd-val and id #lgd-id+ID#
    createSVG: function(legends)
    {
        _.each(dhpPinboardView.filters, function(theLegend, legIndex) {
            var filterTerms = theLegend.terms;
            var legendName = theLegend.name;

                // Create SVG g[roup] for the Legend Head
            legendHtml = jQuery('<div class="'+legendName+' legend-div" id="term-legend-'+legIndex+
                            '"><div class="legend-title">'+legendName+'</div><div class="terms"></div></div>');
                // Create entries for all of the 1st-level terms (do not represent children of terms)
            _.each(filterTerms, function(theTerm) {
                if (legendName !== theTerm.name) {
                    // TO DO: Create SVG groups and markers
                }
            });
        });
    }, // createSVG()


        // PURPOSE: Called by createDataObjects() to take nested array(s) of terms and convert to flat array
        //              of items with fields: id, parent, name, icon_url
        // NOTES:   In array returned by php, parent markers have <id> field but children have <term_id>
        // RETURNS: Object with 2 properties: terms and all
    reformatTerms: function(oldTerms)
    {
        var newTerms = oldTerms;
        var termArray = [];
        var allTerms = [];

        _.each(oldTerms.terms, function(theTerm) {
            termArray.push(theTerm);
            _.each(theTerm.children, function(theChild) {
                termArray.push( {
                    id: theChild.term_id,
                    parent: theTerm.id,
                    icon_url: theTerm.icon_url,    // child inherits parent's viz
                    name: theChild.name
                });
            });
        });

        newTerms.terms = termArray;

        return newTerms;
    }, // reformatTerms()


        // PURPOSE: Handle user selection of legend in navbar menu
        // INPUT:   target = element selected by user
    switchLegend: function(target)
    {
        var newLgdName = jQuery(target).text();

        if (newLgdName !== dhpPinboardView.curLgdName) {
                // Don't display current (or any) Legend in main Legend key
            jQuery('.legend-div').hide();
            jQuery('.legend-div').removeClass('active-legend');

                // Display selected legend (whose ID was stored in href) in main Legend key
            var action = jQuery(target).attr('href');
            jQuery(action).addClass('active-legend');
            jQuery(action).show();

                // Change active menu item
            jQuery('.legend-dropdown > .active').removeClass('active');
            jQuery(target).parent().addClass('active');

                // Update the markers to show on pinboard
            dhpPinboardView.switchFilter(newLgdName);
            dhpPinboardView.dhpUpdateSize();
        }
    }, // switchLegend()


        // PURPOSE: Handle data implications of new Legend/Category
        // INPUT:   filterName = name of legend/category selected
        // ASSUMES: rawAjaxData has been assigned, selectControl has been initialized
        // SIDE-FX: Sets curLgdName, curLgdFilter; modifies svg class settings
    switchFilter: function(filterName)
    {
            // Reset current Legend/Filter variables
        dhpPinboardView.curLgdName = filterName;
        dhpPinboardView.curLgdFilter = _.find(dhpPinboardView.filters, function(theFilter) {
            return theFilter.name === filterName;
        });

console.log("Switch to filter [name]:"+dhpPinboardView.curLgdFilter.name+" [id]:"+dhpPinboardView.curLgdFilter.id);

            // Add "hide" to class of all Legend heads in SVG
        var allGroupings = dhpPinboardView.paper.selectAll('.lgd-head');
        _.each(allGroupings, function(theGrouping, index) {
            theGrouping.addClass('hide');
        });

            // Remove "hide" for this Legend head in SVG
        var thisGroup = dhpPinboardView.paper.select('.lgd-head #lgd-id'+dhpPinboardView.curLgdFilter.terms[0].id);
        if (thisGroup) {
            thisGroup.removeClass('hide');
        } else {
console.log("Bug in Lgd head handling: Legend Head not found");
        }
    }, // switchFilter()


        // PURPOSE: Handle user changing Legend keys in current Legend -- show current marker selection
        // ASSUMES: All Legends but current has "hide" in class
    refreshMarkerLayer: function()
    {
        var grpsToHide, lgdID, grpsToShow;

            // Hide all Legend values in current Legend by default
        grpsToHide = dhpPinboardView.paper.select('#lgd-id'+dhpPinboardView.curLgdFilter.terms[0].id+' .lgd-val');

            // Go through all checked Legend values and remove hide from class of all assoc. markers
        jQuery('#legends .active-legend .compare input:checked').each(function(index) {
            lgdID = jQuery(this).closest('.row').find('.columns .value').data('id');
            grpsToShow = dhpPinboardView.paper.selectAll('#lgd-id'+lgdID);
            _.each(grpsToShow, function(theGrouping, index) {
                theGrouping.addClass('hide');
            });
        });
    }, // refreshMarkerLayer()


        // PURPOSE: Create HTML for all of the legends for this visualization
    createLegends: function() 
    {
        var legendHtml;
        var legendHeight;

            // Build Legend controls on the right (category toggles) for each legend value and insert Legend name into dropdown above
        _.each(dhpPinboardView.filters, function(theLegend, legIndex) {
            var filterTerms = theLegend.terms;
            var legendName = theLegend.name;

                // "Root" DIV for this particular Legend
            legendHtml = jQuery('<div class="'+legendName+' legend-div" id="term-legend-'+legIndex+
                            '"><div class="legend-title">'+legendName+'</div><div class="terms"></div></div>');
                // Create entries for all terms (though 2nd-level children are made invisible)
            _.each(filterTerms, function(theTerm) {
                if (legendName !== theTerm.name) {
                    var hasParentClass = '';
                        // Make 2nd-level children invisible with CSS
                    if(theTerm.parent) {
                        hasParentClass = 'hasParent';
                    }
                    if (theTerm.icon_url == null || theTerm.icon_url == undefined) {
                        throw new Error("Legend value "+theTerm.name+" has not been assigned a color or icon");
                    }
                    var firstIconChar = theTerm.icon_url.substring(0,1);
                    var htmlStr;
                    switch (firstIconChar) {
                    case '#':
                        htmlStr = '<div class="small-3 large-2 columns" style="background:'+
                            theTerm.icon_url+'"><input type="checkbox" checked="checked"></div>';
                        break;
                    case '.':
                        htmlStr = '<div class="small-2 large-1 columns"><div class="maki-icon '+
                            theTerm.icon_url.substring(1)+'"></div></div><input type="checkbox" checked="checked">';
                        break;
                    default:
                            // TO DO: Support uploaded images!
                        // icon = 'background: url(\''+theTerm.icon_url+'\') no-repeat right; background-size: 50%;';
                        throw new Error('Unknown visual feature: '+theTerm.icon_url);
                    }

                        // Append new legend value to menu according to type
                    jQuery('.terms', legendHtml).append('<div class="row compare '+hasParentClass+'">'+htmlStr+
                                                    '<div class="small-9 large-10 columns"><a class="value" data-id="'+
                                                    theTerm.id+'" data-parent="'+theTerm.parent+'">'+theTerm.name+'</a></div></div>');
                }
            });
                // Put the Hide/Show checkbox at beginning of Legend
            jQuery('.terms',legendHtml).prepend(Handlebars.compile(jQuery("#dhp-script-pin-legend-hideshow").html()));
                // Add constructed Legend key to total
            jQuery('#legends .legend-row').append(legendHtml);
                // Add Legend title to dropdown menu in navbar -- make 1st Legend active by default
            var active = (legIndex == 0) ? ' class="active"' : '';
            jQuery('.dhp-nav .legend-dropdown').append('<li'+active+'><a href="#term-legend-'+legIndex+'">'+legendName+'</a></li>');         
        });

            // Hide all Legends, except 0 by default
        jQuery('.legend-div').hide();
        jQuery('#term-legend-0').show();
        jQuery('#term-legend-0').addClass('active-legend');

            // Update checkbox height(varies by theme/browser) 
        dhpPinboardView.checkboxHeight = jQuery('#legends').find('input:checkbox').height();

            //Initialize new foundation elements
        jQuery(document).foundation();

            // For small mobile screens, expand Legend menu on hover
        jQuery('#legends').prepend('<a class="legend-resize btn pull-right" href="#" alt="mini"><i class="fi-arrows-compress"></i></a>');
        if(!jQuery('body').hasClass('isMobile')) {
            jQuery('.legend-resize').hide();
            jQuery('#legends').hover(function() {
                jQuery('.legend-resize').fadeIn(100);
            },
            function() {
                jQuery('.legend-resize').fadeOut(100);
            });
        }

            // Add legend Min-Max expand/contract action
        jQuery('.legend-resize').click(function(){
            if (jQuery('#legends').hasClass('mini')) {
                jQuery('#legends').animate({ height: legendHeight },
                    500,
                    function() {
                        jQuery('#legends').removeClass('mini');
                    });
            } 
            else {
                legendHeight = jQuery('#legends').height();
                jQuery('#legends').addClass('mini');                
                jQuery('#legends').animate({ height: 37 }, 500 );
            }
        });

            // Handle user selection of value name from current Legend
        jQuery('#legends div.terms .row a').click(function(event) {
            var spanName = jQuery(this).data('id');

                // "Hide/Show all" button
            if (spanName==='all') {
                    // Should legend values now be checked or unchecked?
                var boxState = jQuery(this).closest('.row').find('input').prop('checked');
                jQuery('.active-legend .terms .row').find('input').prop('checked',!boxState);
            }
                // a specific legend/category value (ID#)
            else {
                    // uncheck everything
                jQuery('.active-legend .terms input').prop('checked', false);
                jQuery('.active-legend .terms .row.selected').removeClass('selected');
                    // select just this item
                jQuery(this).closest('.row').addClass('selected');
                jQuery(this).closest('.row').find('input').prop('checked', true);

                    // Child terms are hidden in menu -- selects them also automatically if parent is checked
                if (dhpPinboardView.useParent) {
                    jQuery('.active-legend .terms .row').find('*[data-parent="'+spanName+'"]').each(function( index ) {
                        jQuery( this ).closest('.row').find('input').prop('checked',true);
                    }); 
                }
            }
                //update pinboard
            dhpPinboardView.refreshMarkerLayer();
        });

            // Handle user selection of checkbox from current Legend
        jQuery('#legends div.terms input').click(function(event) {
            var checkAll = jQuery(this).closest('.row').hasClass('check-all');
            var boxState = jQuery(this).prop('checked');
            var spanName = jQuery(this).closest('.row').find('a').data('id');
                // "Hide/Show all" checkbox
            if (checkAll) {
                jQuery('.active-legend .terms .row').find('input').prop('checked',boxState);
            }
                // toggle individual terms
            else {
                jQuery('.active-legend .terms .check-all').find('input').prop('checked',false);
                
                    //child terms are now hidden in legend. This selects them if parent is checked
                if (dhpPinboardView.useParent) {
                    jQuery('.active-legend .terms .row').find('*[data-parent="'+spanName+'"]').each(function( index ) {
                        jQuery( this ).closest('.row').find('input').prop('checked',true);
                    });
                }
            }
            dhpPinboardView.refreshMarkerLayer();
        });

            // Handle selection of different Legends from navbar
        jQuery('.dhp-nav .legend-dropdown a').click(function(evt) {
            evt.preventDefault();
            dhpPinboardView.switchLegend(evt.target);
        });

          // Show initial Legend selection and show it as active on the menu
        dhpPinboardView.switchFilter(dhpPinboardView.filters[0].name);
        dhpPinboardView.refreshMarkerLayer();
    }, // createLegends()


        // PURPOSE: Handle user selection of a marker on a map to bring up modal
        // INPUT:   e = event whose target is the feature selected on map
        //             HOWEVER! This also called from hover modal WITHOUT a parameter!
        // ASSUMES: currentFeature is set for reason noted above
    onFeatureSelect: function(e)
    {
        dhpPinboardView.callBacks.showMarkerModal(dhpPinboardView.currentFeature);
    }, // onFeatureSelect()


        // PURPOSE: Resizes pinboard-specific elements when browser size changes
    dhpUpdateSize: function()
    {
        var newRowHeight, checkboxMargin;

            //resize legend term position for long titles
        jQuery('.active-legend .terms').css({top: jQuery('.active-legend .legend-title').height() });

            //resize legend items that are two lines and center checkbox
        jQuery('.active-legend .row').each(function(key,value) {
                //height of row containing text(could be multiple lines)
            newRowHeight   = jQuery('.columns', this).eq(1).height();
                // variable to center checkbox in row
            checkboxMargin = (newRowHeight - dhpPinboardView.checkboxHeight) / 2;
                // set elements in rows with new values
            jQuery('.columns', this).eq(0).height(newRowHeight);
            jQuery('.columns', this).eq(0).find('input').css({'margin-top': checkboxMargin});
        });
    }, // dhpUpdateSize()


        // RETURNS: true if touch is supported (and hence no mouse)
    isTouchDevice: function() {
        // var msTouchEnabled = window.navigator.msMaxTouchPoints;
        // var generalTouchEnabled = "ontouchstart" in document.createElement("div");

        // if (msTouchEnabled || generalTouchEnabled) {
        //     return true;
        // }
        // return false;
        return (('ontouchstart' in window) || (navigator.MaxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0));
    },

        // PURPOSE: Get markers associated with projectID via AJAX, insert into mLayer of map
    loadMarkers: function()
    {
    	jQuery.ajax({
            type: 'POST',
            url: dhpPinboardView.ajaxURL,
            data: {
                action: 'dhpGetMarkers',
                project: dhpPinboardView.projectID,
                index: dhpPinboardView.vizIndex
            },
            success: function(data, textStatus, XMLHttpRequest)
            {
                dhpPinboardView.createDataObjects(JSON.parse(data));
                    // Remove Loading modal
                dhpPinboardView.callBacks.remLoadingModal();
                jQuery('.reveal-modal-bg').remove();
            },
            error: function(XMLHttpRequest, textStatus, errorThrown)
            {
               alert(errorThrown);
            }
        });
    } // loadMarkers()

};
