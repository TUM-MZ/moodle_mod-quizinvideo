// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript library for the quizinvideo module.
 *
 * @package    mod
 * @subpackage quizinvideo
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.mod_quizinvideo = M.mod_quizinvideo || {};
M.mod_quizinvideo.page_index = 1;
M.mod_quizinvideo.init_attempt_form = function(Y) {
    M.core_question_engine.init_form(Y, '#responseform');
    var form = Y.one("#responseform");
    var yui_video = Y.one('#video_content').one('*');
    var isNotComplete = form.all(".que.complete").isEmpty();
    if (isNotComplete){
        form.setStyle("height", yui_video.getComputedStyle("height"));
        form.setStyle("display", "block");
        Y.one('#btn_checkForm').on("click", M.mod_quizinvideo.review_attempt);
    }
    Y.fire(!!M.core.event && M.core.event.FILTER_CONTENT_UPDATED, {nodes: (Y.all('.formulation'))});
    if(!isNotComplete)
        M.mod_quizinvideo.review_attempt();
};

M.mod_quizinvideo.review_attempt = function (e) {
    //write form check code here
    var form = Y.one("#responseform");
    var maindiv = Y.one("div[role=main]");
    var videodiv = maindiv.one("div#video_div");
    var attemptid = maindiv.one("input[name=attempt]").get("value");
    var sesskey = maindiv.one("input[name=sesskey]").get("value");
    var pagediv = form.one(".page");
    var page = parseInt(pagediv.get("id").substr(4)); //4 is "page" string length
    var slots = pagediv.one("input[name=slotsinpage]").get("value");
    Y.use("io-base", 'node', 'array-extras', 'querystring-stringify', function (Y) {
        var cfg, request, uri, query;
        query = Y.Array.reduce(Y.one(form).all('input[name],select[name],textarea[name]')._nodes, {}, function (init, el, index, array) {
            var isCheckable = (el.type == "checkbox" || el.type == "radio");
            if ((isCheckable && el.checked) || !isCheckable) {
                init[el.name] = el.value;
            }
            return init;
        });
        query.attempt = attemptid;
        query.finishattempt = 1;
        query.sesskey = sesskey;
        query.thispage = page - 1;
        query.timeup = 0;
        query.slots = slots;
        var quoted_query = Y.QueryString.stringify(query);
        uri = "processattempt.php" //The PHP page in which you pass the data to
        cfg = {
            method: 'POST',  //you want a POST transaction
            data: quoted_query,  //your data
            on: {
                success: function (a, b) {
                    form.remove();

                    videodiv.insert("<div id='formwithanswer'> </div>", 'after');
                    Y.one('div#formwithanswer').setHTML(b.response);
                    var formwithanswer = Y.one("#formwithanswer");
                    var yui_video = Y.one('#video_content').one("*");
                    formwithanswer.setStyle("height", yui_video.getComputedStyle("height"));
                    formwithanswer.setStyle("display", "block");
                    Y.fire(!!M.core.event && M.core.event.FILTER_CONTENT_UPDATED, {nodes: (Y.all('.formulation'))});
                    Y.one('#btn_continuevideo').on("click", function (e) {
                        M.mod_quizinvideo.page_index++;
                        Y.one("#formwithanswer").remove();

                        var vid = videojs('#video_content');
                        if(vid.currentTime() < vid.duration()){
                            vid.play();
                            M.mod_quizinvideo.paused = false;
                        }
                    });
                },
                failure: function () {
                    console.log("failed");
                }
            }
        };

        request = Y.io(uri, cfg);
    })
};

M.mod_quizinvideo.init_review_form = function(Y) {
    M.core_question_engine.init_form(Y, '.questionflagsaveform');
    Y.on('submit', function(e) { e.halt(); }, '.questionflagsaveform');
};

M.mod_quizinvideo.init_comment_popup = function(Y) {
    // Add a close button to the window.
    var closebutton = Y.Node.create('<input type="button" />');
    closebutton.set('value', M.util.get_string('cancel', 'moodle'));
    Y.one('#id_submitbutton').ancestor().append(closebutton);
    Y.on('click', function() { window.close() }, closebutton);
}

// Code for updating the countdown timer that is used on timed quizinvideos.
M.mod_quizinvideo.timer = {
    // YUI object.
    Y: null,

    // Timestamp at which time runs out, according to the student's computer's clock.
    endtime: 0,

    // Is this a quizinvideo preview?
    preview: 0,

    // This records the id of the timeout that updates the clock periodically,
    // so we can cancel.
    timeoutid: null,

    /**
     * @param Y the YUI object
     * @param start, the timer starting time, in seconds.
     * @param preview, is this a quizinvideo preview?
     */
    init: function(Y, start, preview) {
        M.mod_quizinvideo.timer.Y = Y;
        M.mod_quizinvideo.timer.endtime = M.pageloadstarttime.getTime() + start*1000;
        M.mod_quizinvideo.timer.preview = preview;
        M.mod_quizinvideo.timer.update();
        Y.one('#quizinvideo-timer').setStyle('display', 'block');
    },

    /**
     * Stop the timer, if it is running.
     */
    stop: function(e) {
        if (M.mod_quizinvideo.timer.timeoutid) {
            clearTimeout(M.mod_quizinvideo.timer.timeoutid);
        }
    },

    /**
     * Function to convert a number between 0 and 99 to a two-digit string.
     */
    two_digit: function(num) {
        if (num < 10) {
            return '0' + num;
        } else {
            return num;
        }
    },

    // Function to update the clock with the current time left, and submit the quizinvideo if necessary.
    update: function() {
        var Y = M.mod_quizinvideo.timer.Y;
        var secondsleft = Math.floor((M.mod_quizinvideo.timer.endtime - new Date().getTime())/1000);

        // If time has expired, set the hidden form field that says time has expired and submit
        if (secondsleft < 0) {
            M.mod_quizinvideo.timer.stop(null);
            Y.one('#quizinvideo-time-left').setContent(M.util.get_string('timesup', 'quizinvideo'));
            var input = Y.one('input[name=timeup]');
            input.set('value', 1);
            var form = input.ancestor('form');
            if (form.one('input[name=finishattempt]')) {
                form.one('input[name=finishattempt]').set('value', 0);
            }
            M.core_formchangechecker.set_form_submitted();
            form.submit();
            return;
        }

        // If time has nearly expired, change the colour.
        if (secondsleft < 100) {
            Y.one('#quizinvideo-timer').removeClass('timeleft' + (secondsleft + 2))
                    .removeClass('timeleft' + (secondsleft + 1))
                    .addClass('timeleft' + secondsleft);
        }

        // Update the time display.
        var hours = Math.floor(secondsleft/3600);
        secondsleft -= hours*3600;
        var minutes = Math.floor(secondsleft/60);
        secondsleft -= minutes*60;
        var seconds = secondsleft;
        Y.one('#quizinvideo-time-left').setContent(hours + ':' +
                M.mod_quizinvideo.timer.two_digit(minutes) + ':' +
                M.mod_quizinvideo.timer.two_digit(seconds));

        // Arrange for this method to be called again soon.
        M.mod_quizinvideo.timer.timeoutid = setTimeout(M.mod_quizinvideo.timer.update, 100);
    }
};

M.mod_quizinvideo.nav = M.mod_quizinvideo.nav || {};

M.mod_quizinvideo.nav.update_flag_state = function(attemptid, questionid, newstate) {
    var Y = M.mod_quizinvideo.nav.Y;
    var navlink = Y.one('#quizinvideonavbutton' + questionid);
    navlink.removeClass('flagged');
    if (newstate == 1) {
        navlink.addClass('flagged');
        navlink.one('.accesshide .flagstate').setContent(M.util.get_string('flagged', 'question'));
    } else {
        navlink.one('.accesshide .flagstate').setContent('');
    }
};

M.mod_quizinvideo.nav.init = function(Y) {
    M.mod_quizinvideo.nav.Y = Y;

    Y.all('#quizinvideonojswarning').remove();

    var form = Y.one('#responseform');
    if (form) {
        function find_enabled_submit() {
            // This is rather inelegant, but the CSS3 selector
            //     return form.one('input[type=submit]:enabled');
            // does not work in IE7, 8 or 9 for me.
            var enabledsubmit = null;
            form.all('input[type=submit]').each(function(submit) {
                if (!enabledsubmit && !submit.get('disabled')) {
                    enabledsubmit = submit;
                }
            });
            return enabledsubmit;
        }

        function nav_to_page(pageno) {
            Y.one('#followingpage').set('value', pageno);

            // Automatically submit the form. We do it this strange way because just
            // calling form.submit() does not run the form's submit event handlers.
            var submit = find_enabled_submit();
            submit.set('name', '');
            submit.getDOMNode().click();
        };

        Y.delegate('click', function(e) {
            if (this.hasClass('thispage')) {
                return;
            }

            e.preventDefault();

            var pageidmatch = this.get('href').match(/page=(\d+)/);
            var pageno;
            if (pageidmatch) {
                pageno = pageidmatch[1];
            } else {
                pageno = 0;
            }

            var questionidmatch = this.get('href').match(/#q(\d+)/);
            if (questionidmatch) {
                form.set('action', form.get('action') + '#q' + questionidmatch[1]);
            }

            nav_to_page(pageno);
        }, document.body, '.qnbutton');
    }

    if (Y.one('a.endtestlink')) {
        Y.on('click', function(e) {
            e.preventDefault();
            nav_to_page(-1);
        }, 'a.endtestlink');
    }

    if (M.core_question_flags) {
        M.core_question_flags.add_listener(M.mod_quizinvideo.nav.update_flag_state);
    }
};

M.mod_quizinvideo.secure_window = {
    init: function(Y) {
        if (window.location.href.substring(0, 4) == 'file') {
            window.location = 'about:blank';
        }
        Y.delegate('contextmenu', M.mod_quizinvideo.secure_window.prevent, document, '*');
        Y.delegate('mousedown',   M.mod_quizinvideo.secure_window.prevent_mouse, 'body', '*');
        Y.delegate('mouseup',     M.mod_quizinvideo.secure_window.prevent_mouse, 'body', '*');
        Y.delegate('dragstart',   M.mod_quizinvideo.secure_window.prevent, document, '*');
        Y.delegate('selectstart', M.mod_quizinvideo.secure_window.prevent_selection, document, '*');
        Y.delegate('cut',         M.mod_quizinvideo.secure_window.prevent, document, '*');
        Y.delegate('copy',        M.mod_quizinvideo.secure_window.prevent, document, '*');
        Y.delegate('paste',       M.mod_quizinvideo.secure_window.prevent, document, '*');
        Y.on('beforeprint', function() {
            Y.one(document.body).setStyle('display', 'none');
        }, window);
        Y.on('afterprint', function() {
            Y.one(document.body).setStyle('display', 'block');
        }, window);
        Y.on('key', M.mod_quizinvideo.secure_window.prevent, '*', 'press:67,86,88+ctrl');
        Y.on('key', M.mod_quizinvideo.secure_window.prevent, '*', 'up:67,86,88+ctrl');
        Y.on('key', M.mod_quizinvideo.secure_window.prevent, '*', 'down:67,86,88+ctrl');
        Y.on('key', M.mod_quizinvideo.secure_window.prevent, '*', 'press:67,86,88+meta');
        Y.on('key', M.mod_quizinvideo.secure_window.prevent, '*', 'up:67,86,88+meta');
        Y.on('key', M.mod_quizinvideo.secure_window.prevent, '*', 'down:67,86,88+meta');
    },

    is_content_editable: function(n) {
        if (n.test('[contenteditable=true]')) {
            return true;
        }
        n = n.get('parentNode');
        if (n === null) {
            return false;
        }
        return M.mod_quizinvideo.secure_window.is_content_editable(n);
    },

    prevent_selection: function(e) {
        return false;
    },

    prevent: function(e) {
        alert(M.util.get_string('functiondisabledbysecuremode', 'quizinvideo'));
        e.halt();
    },

    prevent_mouse: function(e) {
        if (e.button == 1 && /^(INPUT|TEXTAREA|BUTTON|SELECT|LABEL|A)$/i.test(e.target.get('tagName'))) {
            // Left click on a button or similar. No worries.
            return;
        }
        if (e.button == 1 && M.mod_quizinvideo.secure_window.is_content_editable(e.target)) {
            // Left click in Atto or similar.
            return;
        }
        e.halt();
    },

    /**
     * Event handler for the quizinvideo start attempt button.
     */
    start_attempt_action: function(e, args) {
        if (args.startattemptwarning == '') {
            openpopup(e, args);
        } else {
            M.util.show_confirm_dialog(e, {
                message: args.startattemptwarning,
                callback: function() {
                    openpopup(e, args);
                },
                continuelabel: M.util.get_string('startattempt', 'quizinvideo')
            });
        }
    },

    init_close_button: function(Y, url) {
        Y.on('click', function(e) {
            M.mod_quizinvideo.secure_window.close(url, 0)
        }, '#secureclosebutton');
    },

    close: function(Y, url, delay) {
        setTimeout(function() {
            if (window.opener) {
                window.opener.document.location.reload();
                window.close();
            } else {
                window.location.href = url;
            }
        }, delay*1000);
    }
};
M.mod_quizinvideo.init_video = function(Y){
    Y.Node.DOM_EVENTS.timeupdate = 1;
    var i = 0;
    var marker_loaded = false;
    window.videojs = videojs;
    var video = videojs("video_content");
    video.markers({markers:[]});
    M.mod_quizinvideo.paused = false;
    var timestamps = Y.all('.timestamp').get("value");
    var timestamp_titles = Y.all('.timestamp_title').get("value");
    video.on('timeupdate', function () {
        if(marker_loaded == false){
            for (var ts in timestamps){
                video.markers.add([{ time: timestamps[ts], text: timestamp_titles[ts]}]);
            }
            marker_loaded = true;
        }
        var currentTime = video.currentTime();
        if(currentTime > timestamps[i] && !M.mod_quizinvideo.paused ){
            M.mod_quizinvideo.paused = true;
            video.pause();
            video.exitFullscreen();
            i++;
            Y.use("io-base", 'node', 'array-extras', 'querystring-stringify', function(Y) {
                var cfg, request;
                var uri = "attemptformrenderer.php"
                var maindiv = Y.one("div[role=main]");
                var attemptid = maindiv.one("input[name=attempt]").get("value");
                var sesskey = maindiv.one("input[name=sesskey]").get("value");
                var is_finished = 0;
                cfg = {
                    method: 'POST',
                    data: {
                        'sesskey': sesskey,
                        'attempt': attemptid,
                        'page': M.mod_quizinvideo.page_index
                    },
                    on:{
                        success:function(a, b){
                            // RegExp to find the script block which redefines require parameters (require variable)
                            var testRequireParams = RegExp('^var require = {(.|\n)*};', 'gm');
                            Y.one("#video_div").insert(b.response, 'after');
                            Y.one("#video_div").ancestor().all('script').each(function(scriptEl) {
                                // add new script elements to head so they get executed
                                var delieveredScriptNode = scriptEl.getDOMNode();
                                var newScriptEl = document.createElement('script');
                                newScriptEl.type = 'text/javascript';
                                if (!delieveredScriptNode.innerHTML) return;
                                // Do not redefine the require parameters
                                if (testRequireParams.test(delieveredScriptNode.innerHTML)) return;
                                newScriptEl.innerHTML = '// -- inserted -- \n' + delieveredScriptNode.innerHTML;
                                document.body.appendChild(newScriptEl);
                            });
                            M.mod_quizinvideo.init_attempt_form(Y);
                        },
                        failure:function(){
                            console.log("loading form failed");
                        }
                    }
                };
                request = Y.io(uri, cfg);
            });
        }
    });

    video.on('seeked', function(){
        var currentTime= video.currentTime();
        for(var j = 0; j < timestamps.length; j++){
            if(currentTime < timestamps[j]){
                i = j;
                M.mod_quizinvideo.page_index = i+1;
                break;
            }
        }
    });

};
