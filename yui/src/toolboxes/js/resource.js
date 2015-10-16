/**
 * Resource and activity toolbox class.
 *
 * This class is responsible for managing AJAX interactions with activities and resources
 * when viewing a quizinvideo in editing mode.
 *
 * @module mod_quizinvideo-resource-toolbox
 * @namespace M.mod_quizinvideo.resource_toolbox
 */

/**
 * Resource and activity toolbox class.
 *
 * This is a class extending TOOLBOX containing code specific to resources
 *
 * This class is responsible for managing AJAX interactions with activities and resources
 * when viewing a quizinvideo in editing mode.
 *
 * @class resources
 * @constructor
 * @extends M.course.toolboxes.toolbox
 */
var RESOURCETOOLBOX = function() {
    RESOURCETOOLBOX.superclass.constructor.apply(this, arguments);
};

Y.extend(RESOURCETOOLBOX, TOOLBOX, {
    /**
     * An Array of events added when editing a max mark field.
     * These should all be detached when editing is complete.
     *
     * @property editmaxmarkevents
     * @protected
     * @type Array
     * @protected
     */
    editmaxmarkevents: [],
    edittimeofvideoevents: [],

    /**
     *
     */
    NODE_PAGE: 1,
    NODE_SLOT: 2,
    NODE_JOIN: 3,

    /**
     * Initialize the resource toolbox
     *
     * For each activity the commands are updated and a reference to the activity is attached.
     * This way it doesn't matter where the commands are going to called from they have a reference to the
     * activity that they relate to.
     * This is essential as some of the actions are displayed in an actionmenu which removes them from the
     * page flow.
     *
     * This function also creates a single event delegate to manage all AJAX actions for all activities on
     * the page.
     *
     * @method initializer
     * @protected
     */
    initializer: function() {
        M.mod_quizinvideo.quizinvideobase.register_module(this);
        BODY.delegate('key', this.handle_data_action, 'down:enter', SELECTOR.ACTIVITYACTION, this);
        Y.delegate('click', this.handle_data_action, BODY, SELECTOR.ACTIVITYACTION, this);
        //BODY.delegate('key', this.handle_data_action, 'down:enter', SELECTOR.ACTIVITYACTIONTIME, this);
        //Y.delegate('click', this.handle_data_action, BODY, SELECTOR.ACTIVITYACTIONTIME, this);
    },

    /**
     * Handles the delegation event. When this is fired someone has triggered an action.
     *
     * Note not all actions will result in an AJAX enhancement.
     *
     * @protected
     * @method handle_data_action
     * @param {EventFacade} ev The event that was triggered.
     * @returns {boolean}
     */
    handle_data_action: function(ev) {
        // We need to get the anchor element that triggered this event.
        var node = ev.target;
        if (!node.test('a')) {
            node = node.ancestor(SELECTOR.ACTIVITYACTION);
        }

        // From the anchor we can get both the activity (added during initialisation) and the action being
        // performed (added by the UI as a data attribute).
        var action = node.getData('action'),
            activity = node.ancestor(SELECTOR.ACTIVITYLI);

        if (!node.test('a') || !action || !activity) {
            // It wasn't a valid action node.
            return;
        }

        // Switch based upon the action and do the desired thing.
        switch (action) {
            case 'editmaxmark':
                // The user wishes to edit the maxmark of the resource.
                this.edit_maxmark(ev, node, activity, action);
                break;
            case 'edittimeofvideo':
                // The user wishes to edit the timeofvideo of the page.
                this.edit_timeofvideo(ev, node, activity, action);
                break;
            case 'copytimeofvideo':
                //this user wishes to copy timestamp from video
                this.copy_timefromvideo(ev, node, activity, action);
                break;
            case 'delete':
                // The user is deleting the activity.
                this.delete_with_confirmation(ev, node, activity, action);
                break;
            case 'addpagebreak':
            case 'removepagebreak':
                // The user is adding or removing a page break.
                this.update_page_break(ev, node, activity, action);
                break;
            default:
                // Nothing to do here!
                break;
        }
    },

    /**
     * Add a loading icon to the specified activity.
     *
     * The icon is added within the action area.
     *
     * @method add_spinner
     * @param {Node} activity The activity to add a loading icon to
     * @return {Node|null} The newly created icon, or null if the action area was not found.
     */
    add_spinner: function(activity) {
        var actionarea = activity.one(SELECTOR.ACTIONAREA);
        if (actionarea) {
            return M.util.add_spinner(Y, actionarea);
        }
        return null;
    },

    /**
     * Deletes the given activity or resource after confirmation.
     *
     * @protected
     * @method delete_with_confirmation
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @chainable
     */
    delete_with_confirmation: function(ev, button, activity) {
        // Prevent the default button action.
        ev.preventDefault();

        // Get the element we're working on.
        var element   = activity,
            // Create confirm string (different if element has or does not have name)
            confirmstring = '',
            qtypename = M.util.get_string('pluginname',
                        'qtype_' + element.getAttribute('class').match(/qtype_([^\s]*)/)[1]);
        confirmstring = M.util.get_string('confirmremovequestion', 'quizinvideo', qtypename);

        // Create the confirmation dialogue.
        var confirm = new M.core.confirm({
            question: confirmstring,
            modal: true
        });

        // If it is confirmed.
        confirm.on('complete-yes', function() {

            var spinner = this.add_spinner(element);
            var data = {
                'class': 'resource',
                'action': 'DELETE',
                'id': Y.Moodle.mod_quizinvideo.util.slot.getId(element)
            };
            this.send_request(data, spinner, function(response) {
                if (response.deleted) {
                    // Actually remove the element.
                    Y.Moodle.mod_quizinvideo.util.slot.remove(element);
                    this.reorganise_edit_page();
                    if (M.core.actionmenu && M.core.actionmenu.instance) {
                        M.core.actionmenu.instance.hideMenu();
                    }
                } else {
                    window.location.reload(true);
                }
            });

        }, this);

        return this;
    },


    /**
     * Edit the maxmark for the resource
     *
     * @protected
     * @method edit_maxmark
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @param {String} action The action that has been requested.
     * @return Boolean
     */
    edit_maxmark : function(ev, button, activity) {
        // Get the element we're working on
        var activityid = Y.Moodle.mod_quizinvideo.util.slot.getId(activity),
            instancemaxmark  = activity.one(SELECTOR.INSTANCEMAXMARK),
            instance = activity.one(SELECTOR.ACTIVITYINSTANCE),
            currentmaxmark = instancemaxmark.get('firstChild'),
            oldmaxmark = currentmaxmark.get('data'),
            maxmarktext = oldmaxmark,
            thisevent,
            anchor = instancemaxmark,// Grab the anchor so that we can swap it with the edit form.
            data = {
                'class'   : 'resource',
                'field'   : 'getmaxmark',
                'id'      : activityid
            };

        // Prevent the default actions.
        ev.preventDefault();

        this.send_request(data, null, function(response) {
            if (M.core.actionmenu && M.core.actionmenu.instance) {
                M.core.actionmenu.instance.hideMenu();
            }

            // Try to retrieve the existing string from the server.
            if (response.instancemaxmark) {
                maxmarktext = response.instancemaxmark;
            }

            // Create the editor and submit button.
            var editform = Y.Node.create('<form action="#" />');
            var editinstructions = Y.Node.create('<span class="' + CSS.EDITINSTRUCTIONS + '" id="id_editinstructions" />')
                .set('innerHTML', M.util.get_string('edittitleinstructions', 'moodle'));
            var editor = Y.Node.create('<input name="maxmark" type="text" class="' + CSS.TITLEEDITOR + '" />').setAttrs({
                'value' : maxmarktext,
                'autocomplete' : 'off',
                'aria-describedby' : 'id_editinstructions',
                'maxLength' : '12',
                'size' : parseInt(this.get('config').questiondecimalpoints, 10) + 2
            });

            // Clear the existing content and put the editor in.
            editform.appendChild(editor);
            editform.setData('anchor', anchor);
            instance.insert(editinstructions, 'before');
            anchor.replace(editform);

            // Force the editing instruction to match the mod-indent position.
            var padside = 'left';
            if (right_to_left()) {
                padside = 'right';
            }

            // We hide various components whilst editing:
            activity.addClass(CSS.EDITINGMAXMARK);

            // Focus and select the editor text.
            editor.focus().select();

            // Cancel the edit if we lose focus or the escape key is pressed.
            thisevent = editor.on('blur', this.edit_maxmark_cancel, this, activity, false);
            this.editmaxmarkevents.push(thisevent);
            thisevent = editor.on('key', this.edit_maxmark_cancel, 'esc', this, activity, true);
            this.editmaxmarkevents.push(thisevent);

            // Handle form submission.
            thisevent = editform.on('submit', this.edit_maxmark_submit, this, activity, oldmaxmark);
            this.editmaxmarkevents.push(thisevent);
        });
    },

    /**
     * Handles the submit event when editing the activity or resources maxmark.
     *
     * @protected
     * @method edit_maxmark_submit
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} activity The activity whose maxmark we are altering.
     * @param {String} originalmaxmark The original maxmark the activity or resource had.
     */
    edit_maxmark_submit : function(ev, activity, originalmaxmark) {
        // We don't actually want to submit anything.
        ev.preventDefault();
        var newmaxmark = Y.Lang.trim(activity.one(SELECTOR.ACTIVITYFORM + ' ' + SELECTOR.ACTIVITYMAXMARK).get('value'));
        var spinner = this.add_spinner(activity);
        this.edit_maxmark_clear(activity);
        activity.one(SELECTOR.INSTANCEMAXMARK).setContent(newmaxmark);
        if (newmaxmark !== null && newmaxmark !== "" && newmaxmark !== originalmaxmark) {
            var data = {
                'class'   : 'resource',
                'field'   : 'updatemaxmark',
                'maxmark'   : newmaxmark,
                'id'      : Y.Moodle.mod_quizinvideo.util.slot.getId(activity)
            };
            this.send_request(data, spinner, function(response) {
                if (response.instancemaxmark) {
                    activity.one(SELECTOR.INSTANCEMAXMARK).setContent(response.instancemaxmark);
                }
            });
        }
    },

    /**
     * Handles the cancel event when editing the activity or resources maxmark.
     *
     * @protected
     * @method edit_maxmark_cancel
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} activity The activity whose maxmark we are altering.
     * @param {Boolean} preventdefault If true we should prevent the default action from occuring.
     */
    edit_maxmark_cancel : function(ev, activity, preventdefault) {
        if (preventdefault) {
            ev.preventDefault();
        }
        this.edit_maxmark_clear(activity);
    },

    /**
     * Handles clearing the editing UI and returning things to the original state they were in.
     *
     * @protected
     * @method edit_maxmark_clear
     * @param {Node} activity  The activity whose maxmark we were altering.
     */
    edit_maxmark_clear : function(activity) {
        // Detach all listen events to prevent duplicate triggers
        new Y.EventHandle(this.editmaxmarkevents).detach();

        var editform = activity.one(SELECTOR.ACTIVITYFORM),
            instructions = activity.one('#id_editinstructions');
        if (editform) {
            editform.replace(editform.getData('anchor'));
        }
        if (instructions) {
            instructions.remove();
        }

        // Remove the editing class again to revert the display.
        activity.removeClass(CSS.EDITINGMAXMARK);

        // Refocus the link which was clicked originally so the user can continue using keyboard nav.
        Y.later(100, this, function() {
            activity.one(SELECTOR.EDITMAXMARK).focus();
        });

        // This hack is to keep Behat happy until they release a version of
        // MinkSelenium2Driver that fixes
        // https://github.com/Behat/MinkSelenium2Driver/issues/80.
        if (!Y.one('input[name=maxmark')) {
            Y.one('body').append('<input type="text" name="maxmark" style="display: none">');
        }
    },

    /**
     * Copy the time from video for the resource
     *
     * @protected
     * @method edit_maxmark
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @param {String} action The action that has been requested.
     * @return Boolean
     */
    copy_timefromvideo : function(ev, button, activity) {
        ev.preventDefault();
        var video = videojs(CSS.VIDEO);
        var page = Y.Moodle.mod_quizinvideo.util.page.getId(activity);
        var spinner = this.add_spinner(activity);
        var newtimeofvideo = video.currentTime();

        if (newtimeofvideo !== null && newtimeofvideo !== "") {
            var data = {
                'class'   : 'resource',
                'field'   : 'updatetimeofvideo',
                'timeofvideo'   : newtimeofvideo,
                'page'  : page,
                'id'      : Y.Moodle.mod_quizinvideo.util.page.getId(activity)
            };
            this.send_request(data, spinner, function(response) {
                if (response.instance_timeofvideo) {
                    activity.one(SELECTOR.INSTANCETIMEOFVIDEO).setContent(response.instance_timeofvideo.toFixed(2));
                }
            });
        }

    },

    /**
     * Edit the timeofvideo for the resource
     *
     * @protected
     * @method edit_maxmark
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @param {String} action The action that has been requested.
     * @return Boolean
     */
    edit_timeofvideo : function(ev, button, activity) {
        // Get the element we're working on
        var pageid = Y.Moodle.mod_quizinvideo.util.page.getId(activity),
            instancetimeofvideo  = activity.one(SELECTOR.INSTANCETIMEOFVIDEO),
            instance = activity.one(SELECTOR.TIMECONTAINER),
            currenttimeofvideo = instancetimeofvideo.get('firstChild');
        var oldtimeofvideo;
        if(currenttimeofvideo){
            oldtimeofvideo= currenttimeofvideo.get('data');
        }
        else{
            oldtimeofvideo= '';
        }

        var timeofvideotext = oldtimeofvideo;
        var thisevent;
        var anchor = instancetimeofvideo;// Grab the anchor so that we can swap it with the edit form.
        var data = {
            'class'   : 'resource',
            'field'   : 'gettimeofvideo',
            'page'      : pageid
        };

        // Prevent the default actions.
        ev.preventDefault();

        this.send_request(data, null, function(response) {
            if (M.core.actionmenu && M.core.actionmenu.instance) {
                M.core.actionmenu.instance.hideMenu();
            }

            // Try to retrieve the existing string from the server.
            if (response.instancetimeofvideo) {
                timeofvideotext = response.instancetimeofvideo;
            }

            // Create the editor and submit button.
            var editform = Y.Node.create('<form action="#" />');
            var editinstructions = Y.Node.create('<span class="' + CSS.EDITTIMEINSTRUCTIONS + '" id="id_editinstructions" />')
                .set('innerHTML', M.util.get_string('edittitleinstructions', 'moodle'));
            var editor = Y.Node.create('<input name="timeofvideo" type="text" class="' + CSS.TITLEEDITOR + '" />').setAttrs({
                'value' : timeofvideotext,
                'autocomplete' : 'off',
                'aria-describedby' : 'id_editinstructions',
                'maxLength' : '9',
                'size' : '9'
            });

            // Clear the existing content and put the editor in.
            editform.appendChild(editor);
            editform.setData('anchor', anchor);
            instance.insert(editinstructions, 'after');
            anchor.replace(editform);

            // Force the editing instruction to match the mod-indent position.
            var padside = 'left';
            if (right_to_left()) {
                padside = 'right';
            }

            // We hide various components whilst editing:
            activity.addClass(CSS.EDITINGMAXMARK);

            // Focus and select the editor text.
            editor.focus().select();

            // Cancel the edit if we lose focus or the escape key is pressed.
            thisevent = editor.on('blur', this.edit_timeofvideo_cancel, this, activity, false);
            this.edittimeofvideoevents.push(thisevent);
            thisevent = editor.on('key', this.edit_timeofvideo_cancel, 'esc', this, activity, true);
            this.edittimeofvideoevents.push(thisevent);

            // Handle form submission.
            thisevent = editform.on('submit', this.edit_timeofvideo_submit, this, activity, oldtimeofvideo);
            this.edittimeofvideoevents.push(thisevent);
        });
    },
    /**
     * Handles the submit event when editing the activity or resources maxmark.
     *
     * @protected
     * @method edit_maxmark_submit
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} activity The activity whose maxmark we are altering.
     * @param {String} originaltimeofvideo The original maxmark the activity or resource had.
     */
    edit_timeofvideo_submit : function(ev, activity, originaltimeofvideo) {
        // We don't actually want to submit anything.
        ev.preventDefault();
        var newtimeofvideo= Y.Lang.trim(activity.one(SELECTOR.ACTIVITYFORMTIME + ' ' + SELECTOR.ACTIVITYTIMEOFVIDEO).get('value'));
        var page = Y.Moodle.mod_quizinvideo.util.page.getId(activity);
        var video = videojs(CSS.VIDEO);
        var maxtime = video.duration();
        var spinner = this.add_spinner(activity);
        this.edit_timeofvideo_clear(activity);
        activity.one(SELECTOR.INSTANCETIMEOFVIDEO).setContent(newtimeofvideo);
        if (newtimeofvideo !== null && newtimeofvideo !== "" && newtimeofvideo !== originaltimeofvideo && newtimeofvideo <= maxtime && newtimeofvideo >= 0) {
            var data = {
                'class'   : 'resource',
                'field'   : 'updatetimeofvideo',
                'timeofvideo'   : newtimeofvideo,
                'page'  : page,
                'id'      : Y.Moodle.mod_quizinvideo.util.page.getId(activity)
            };
            this.send_request(data, spinner, function(response) {
                if (response.instance_timeofvideo >= 0) {
                    activity.one(SELECTOR.INSTANCETIMEOFVIDEO).setContent(response.instance_timeofvideo.toFixed(2));
                }
                else{
                    activity.one(SELECTOR.INSTANCETIMEOFVIDEO).setContent(originaltimeofvideo);
                }
            });
        }
        else{
            activity.one(SELECTOR.INSTANCETIMEOFVIDEO).setContent(originaltimeofvideo);
        }

    },

    /**
     * Handles the cancel event when editing the activity or resources maxmark.
     *
     * @protected
     * @method edit_maxmark_cancel
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} activity The activity whose maxmark we are altering.
     * @param {Boolean} preventdefault If true we should prevent the default action from occuring.
     */
    edit_timeofvideo_cancel : function(ev, activity, preventdefault) {
        if (preventdefault) {
            ev.preventDefault();
        }
        this.edit_timeofvideo_clear(activity);
    },

    /**
     * Handles clearing the editing UI and returning things to the original state they were in.
     *
     * @protected
     * @method edit_maxmark_clear
     * @param {Node} activity  The activity whose maxmark we were altering.
     */
    edit_timeofvideo_clear : function(activity) {
        // Detach all listen events to prevent duplicate triggers
        new Y.EventHandle(this.edittimeofvideoevents).detach();

        var editform = activity.one(SELECTOR.ACTIVITYFORMTIME),
            instructions = activity.one('#id_editinstructions');
        if (editform) {
            editform.replace(editform.getData('anchor'));
        }
        if (instructions) {
            instructions.remove();
        }

        // Remove the editing class again to revert the display.
        activity.removeClass(CSS.EDITINGMAXMARK);

        // Refocus the link which was clicked originally so the user can continue using keyboard nav.
        Y.later(100, this, function() {
            activity.one(SELECTOR.EDITTIMEOFVIDEO).focus();
        });

        // This hack is to keep Behat happy until they release a version of
        // MinkSelenium2Driver that fixes
        // https://github.com/Behat/MinkSelenium2Driver/issues/80.
        if (!Y.one('input[name=timeofvideo')) {
            Y.one('body').append('<input type="text" name="timeofvideo" style="display: none">');
        }
    },

    /**
     * Joins or separates the given slot with the page of the previous slot. Reorders the pages of
     * the other slots
     *
     * @protected
     * @method update_page_break
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @chainable
     */
    update_page_break: function(ev, button, activity, action) {
        // Prevent the default button action
        ev.preventDefault();

        nextactivity = activity.next('li.activity.slot');
        var spinner = this.add_spinner(nextactivity),
            slotid = 0;
        var value = action === 'removepagebreak' ? 1 : 2;

        var data = {
            'class': 'resource',
            'field': 'updatepagebreak',
            'id':    slotid,
            'value': value
        };

        slotid = Y.Moodle.mod_quizinvideo.util.slot.getId(nextactivity);
        if (slotid) {
            data.id = Number(slotid);
        }
        this.send_request(data, spinner, function(response) {
            if (response.slots) {
                if (action === 'addpagebreak') {
                    Y.Moodle.mod_quizinvideo.util.page.add(activity);
                } else {
                    var page = activity.next(Y.Moodle.mod_quizinvideo.util.page.SELECTORS.PAGE);
                    Y.Moodle.mod_quizinvideo.util.page.remove(page, true);
                }
                this.reorganise_edit_page();
            } else {
                window.location.reload(true);
            }
        });

        return this;
    },

    /**
     * Reorganise the UI after every edit action.
     *
     * @protected
     * @method reorganise_edit_page
     */
    reorganise_edit_page: function() {
        Y.Moodle.mod_quizinvideo.util.slot.reorderSlots();
        Y.Moodle.mod_quizinvideo.util.slot.reorderPageBreaks();
        Y.Moodle.mod_quizinvideo.util.page.reorderPages();
        this.get_time_from_db_for_all_pages();
    },
    get_time_from_db_for_all_pages: function(){

    },

    NAME : 'mod_quizinvideo-resource-toolbox',
    ATTRS : {
        courseid : {
            'value' : 0
        },
        quizinvideoid : {
            'value' : 0
        }
    }
});

M.mod_quizinvideo.resource_toolbox = null;
M.mod_quizinvideo.init_resource_toolbox = function(config) {
    M.mod_quizinvideo.resource_toolbox = new RESOURCETOOLBOX(config);
    return M.mod_quizinvideo.resource_toolbox;
};
