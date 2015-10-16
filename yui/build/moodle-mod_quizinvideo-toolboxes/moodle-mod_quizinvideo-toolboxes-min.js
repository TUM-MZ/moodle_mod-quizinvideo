YUI.add("moodle-mod_quizinvideo-toolboxes",function(e,t){var n={ACTIVITYINSTANCE:"activityinstance",AVAILABILITYINFODIV:"div.availabilityinfo",CONTENTWITHOUTLINK:"contentwithoutlink",CONDITIONALHIDDEN:"conditionalhidden",DIMCLASS:"dimmed",DIMMEDTEXT:"dimmed_text",EDITINSTRUCTIONS:"editinstructions",EDITTIMEINSTRUCTIONS:"edittimeinstructions",EDITINGMAXMARK:"editor_displayed",HIDE:"hide",JOIN:"page_join",MODINDENTCOUNT:"mod-indent-",MODINDENTHUGE:"mod-indent-huge",MODULEIDPREFIX:"slot-",PAGE:"page",SECTIONHIDDENCLASS:"hidden",SECTIONIDPREFIX:"section-",SLOT:"slot",SHOW:"editing_show",VIDEO:"video_content",TITLEEDITOR:"titleeditor"},r={ACTIONAREA:".actions",ACTIONLINKTEXT:".actionlinktext",ACTIVITYACTION:"a.cm-edit-action[data-action], a.editing_maxmark, a.editing_timeofvideo, a.copying_timeofvideo",TIMECONTAINER:"span.instancetimeofvideocontainer",ACTIVITYFORM:"span.instancemaxmarkcontainer form",ACTIVITYFORMTIME:"span.instancetimeofvideocontainer form",ACTIVITYICON:"img.activityicon",ACTIVITYINSTANCE:"."+n.ACTIVITYINSTANCE,ACTIVITYLINK:"."+n.ACTIVITYINSTANCE+" > a",ACTIVITYLI:"li.activity",ACTIVITYMAXMARK:"input[name=maxmark]",ACTIVITYTIMEOFVIDEO:"input[name=timeofvideo]",COMMANDSPAN:".commands",CONTENTAFTERLINK:"div.contentafterlink",CONTENTWITHOUTLINK:"div.contentwithoutlink",EDITMAXMARK:"a.editing_maxmark",EDITTIMEOFVIDEO:"a.editing_timeofvideo",HIDE:"a.editing_hide",HIGHLIGHT:"a.editing_highlight",INSTANCENAME:"span.instancename",INSTANCEMAXMARK:"span.instancemaxmark",INSTANCETIMEOFVIDEO:"span.instance_timeofvideo",MODINDENTDIV:".mod-indent",MODINDENTOUTER:".mod-indent-outer",NUMQUESTIONS:".numberofquestions",PAGECONTENT:"div#page-content",PAGELI:"li.page",SECTIONUL:"ul.section",SHOW:"a."+n.SHOW,SHOWHIDE:"a.editing_showhide",SLOTLI:"li.slot",SUMMARKS:".mod_quizinvideo_summarks"},i=e.one(document.body);M.mod_quizinvideo=M.mod_quizinvideo||{};var s=function(){s.superclass.constructor.apply(this,arguments)};e.extend(s,e.Base,{send_request:function(t,n,i,s){t||(t={});var o=this.get("config").pageparams,u;for(u in o)t[u]=o[u];t.sesskey=M.cfg.sesskey,t.courseid=this.get("courseid"),t.quizinvideoid=this.get("quizinvideoid");var a=M.cfg.wwwroot+this.get("ajaxurl"),f=[],l={method:"POST",data:t,on:{success:function(t,s){try{f=e.JSON.parse(s.responseText),f.error&&new M.core.ajaxException(f)}catch(o){}f.hasOwnProperty("newsummarks")&&e.one(r.SUMMARKS).setHTML(f.newsummarks),f.hasOwnProperty("newnumquestions")&&e.one(r.NUMQUESTIONS).setHTML(M.util.get_string("numquestionsx","quizinvideo",f.newnumquestions)),i&&e.bind(i,this,f)(),n&&window.setTimeout(function(){n.hide()},400)},failure:function(e,t){n&&n.hide(),new M.core.ajaxException(t)}},context:this};if(s)for(u in s)l[u]=s[u];return n&&n.show(),e.io(a,l),this}},{NAME:"mod_quizinvideo-toolbox",ATTRS:{courseid:{value:0},quizinvideoid:{value:0},ajaxurl:{value:null},config:{value:{}}}});var o=function(){o.superclass.constructor.apply(this,arguments)};e.extend(o,s,{editmaxmarkevents:[],edittimeofvideoevents:[],NODE_PAGE:1,NODE_SLOT:2,NODE_JOIN:3,initializer:function(){M.mod_quizinvideo.quizinvideobase.register_module(this),i.delegate("key",this.handle_data_action,"down:enter",r.ACTIVITYACTION,this),e.delegate("click",this.handle_data_action,i,r.ACTIVITYACTION,this)},handle_data_action:function(e){var t=e.target;t.test("a")||(t=t.ancestor(r.ACTIVITYACTION));var n=t.getData("action"),i=t.ancestor(r.ACTIVITYLI);if(!t.test("a")||!n||!i)return;switch(n){case"editmaxmark":this.edit_maxmark(e,t,i,n);break;case"edittimeofvideo":this.edit_timeofvideo(e,t,i,n);break;case"copytimeofvideo":this.copy_timefromvideo(e,t,i,n);break;case"delete":this.delete_with_confirmation(e,t,i,n);break;case"addpagebreak":case"removepagebreak":this.update_page_break(e,t,i,n);break;default:}},add_spinner:function(t){var n=t.one(r.ACTIONAREA);return n?M.util.add_spinner(e,n):null},delete_with_confirmation:function(t,n,r){t.preventDefault();var i=r,s="",o=M.util.get_string("pluginname","qtype_"+i.getAttribute("class").match(/qtype_([^\s]*)/)[1]);s=M.util.get_string("confirmremovequestion","quizinvideo",o);var u=new M.core.confirm({question:s,modal:!0});return u.on("complete-yes",function(){var t=this.add_spinner(i),n={"class":"resource",action:"DELETE",id:e.Moodle.mod_quizinvideo.util.slot.getId(i)};this.send_request(n,t,function(t){t.deleted?(e.Moodle.mod_quizinvideo.util.slot.remove(i),this.reorganise_edit_page(),M.core.actionmenu&&M.core.actionmenu.instance&&M.core.actionmenu.instance.hideMenu()):window.location.reload(!0)})},this),this},edit_maxmark:function(t,i,s){var o=e.Moodle.mod_quizinvideo.util.slot.getId(s),u=s.one(r.INSTANCEMAXMARK),a=s.one(r.ACTIVITYINSTANCE),f=u.get("firstChild"),l=f.get("data"),c=l,h,p=u,d={"class":"resource",field:"getmaxmark",id:o};t.preventDefault(),this.send_request(d,null,function(t){M.core.actionmenu&&M.core.actionmenu.instance&&M.core.actionmenu.instance.hideMenu(),t.instancemaxmark&&(c=t.instancemaxmark);var r=e.Node.create('<form action="#" />'),i=e.Node.create('<span class="'+n.EDITINSTRUCTIONS+'" id="id_editinstructions" />').set("innerHTML",M.util.get_string("edittitleinstructions","moodle")),o=e.Node.create('<input name="maxmark" type="text" class="'+n.TITLEEDITOR+'" />').setAttrs({value:c,autocomplete:"off","aria-describedby":"id_editinstructions",maxLength:"12",size:parseInt(this.get("config").questiondecimalpoints,10)+2});r.appendChild(o),r.setData("anchor",p),a.insert(i,"before"),p.replace(r);var u="left";right_to_left()&&(u="right"),s.addClass(n.EDITINGMAXMARK),o.focus().select(),h=o.on("blur",this.edit_maxmark_cancel,this,s,!1),this.editmaxmarkevents.push(h),h=o.on("key",this.edit_maxmark_cancel,"esc",this,s,!0),this.editmaxmarkevents.push(h),h=r.on("submit",this.edit_maxmark_submit,this,s,l),this.editmaxmarkevents.push(h)})},edit_maxmark_submit:function(t,n,i){t.preventDefault();var s=e.Lang.trim(n.one(r.ACTIVITYFORM+" "+r.ACTIVITYMAXMARK).get("value")),o=this.add_spinner(n);this.edit_maxmark_clear(n),n.one(r.INSTANCEMAXMARK).setContent
(s);if(s!==null&&s!==""&&s!==i){var u={"class":"resource",field:"updatemaxmark",maxmark:s,id:e.Moodle.mod_quizinvideo.util.slot.getId(n)};this.send_request(u,o,function(e){e.instancemaxmark&&n.one(r.INSTANCEMAXMARK).setContent(e.instancemaxmark)})}},edit_maxmark_cancel:function(e,t,n){n&&e.preventDefault(),this.edit_maxmark_clear(t)},edit_maxmark_clear:function(t){(new e.EventHandle(this.editmaxmarkevents)).detach();var i=t.one(r.ACTIVITYFORM),s=t.one("#id_editinstructions");i&&i.replace(i.getData("anchor")),s&&s.remove(),t.removeClass(n.EDITINGMAXMARK),e.later(100,this,function(){t.one(r.EDITMAXMARK).focus()}),e.one("input[name=maxmark")||e.one("body").append('<input type="text" name="maxmark" style="display: none">')},copy_timefromvideo:function(t,i,s){t.preventDefault();var o=videojs(n.VIDEO),u=e.Moodle.mod_quizinvideo.util.page.getId(s),a=this.add_spinner(s),f=o.currentTime();if(f!==null&&f!==""){var l={"class":"resource",field:"updatetimeofvideo",timeofvideo:f,page:u,id:e.Moodle.mod_quizinvideo.util.page.getId(s)};this.send_request(l,a,function(e){e.instance_timeofvideo&&s.one(r.INSTANCETIMEOFVIDEO).setContent(e.instance_timeofvideo.toFixed(2))})}},edit_timeofvideo:function(t,i,s){var o=e.Moodle.mod_quizinvideo.util.page.getId(s),u=s.one(r.INSTANCETIMEOFVIDEO),a=s.one(r.TIMECONTAINER),f=u.get("firstChild"),l;f?l=f.get("data"):l="";var c=l,h,p=u,d={"class":"resource",field:"gettimeofvideo",page:o};t.preventDefault(),this.send_request(d,null,function(t){M.core.actionmenu&&M.core.actionmenu.instance&&M.core.actionmenu.instance.hideMenu(),t.instancetimeofvideo&&(c=t.instancetimeofvideo);var r=e.Node.create('<form action="#" />'),i=e.Node.create('<span class="'+n.EDITTIMEINSTRUCTIONS+'" id="id_editinstructions" />').set("innerHTML",M.util.get_string("edittitleinstructions","moodle")),o=e.Node.create('<input name="timeofvideo" type="text" class="'+n.TITLEEDITOR+'" />').setAttrs({value:c,autocomplete:"off","aria-describedby":"id_editinstructions",maxLength:"9",size:"9"});r.appendChild(o),r.setData("anchor",p),a.insert(i,"after"),p.replace(r);var u="left";right_to_left()&&(u="right"),s.addClass(n.EDITINGMAXMARK),o.focus().select(),h=o.on("blur",this.edit_timeofvideo_cancel,this,s,!1),this.edittimeofvideoevents.push(h),h=o.on("key",this.edit_timeofvideo_cancel,"esc",this,s,!0),this.edittimeofvideoevents.push(h),h=r.on("submit",this.edit_timeofvideo_submit,this,s,l),this.edittimeofvideoevents.push(h)})},edit_timeofvideo_submit:function(t,i,s){t.preventDefault();var o=e.Lang.trim(i.one(r.ACTIVITYFORMTIME+" "+r.ACTIVITYTIMEOFVIDEO).get("value")),u=e.Moodle.mod_quizinvideo.util.page.getId(i),a=videojs(n.VIDEO),f=a.duration(),l=this.add_spinner(i);this.edit_timeofvideo_clear(i),i.one(r.INSTANCETIMEOFVIDEO).setContent(o);if(o!==null&&o!==""&&o!==s&&o<=f&&o>=0){var c={"class":"resource",field:"updatetimeofvideo",timeofvideo:o,page:u,id:e.Moodle.mod_quizinvideo.util.page.getId(i)};this.send_request(c,l,function(e){e.instance_timeofvideo>=0?i.one(r.INSTANCETIMEOFVIDEO).setContent(e.instance_timeofvideo.toFixed(2)):i.one(r.INSTANCETIMEOFVIDEO).setContent(s)})}else i.one(r.INSTANCETIMEOFVIDEO).setContent(s)},edit_timeofvideo_cancel:function(e,t,n){n&&e.preventDefault(),this.edit_timeofvideo_clear(t)},edit_timeofvideo_clear:function(t){(new e.EventHandle(this.edittimeofvideoevents)).detach();var i=t.one(r.ACTIVITYFORMTIME),s=t.one("#id_editinstructions");i&&i.replace(i.getData("anchor")),s&&s.remove(),t.removeClass(n.EDITINGMAXMARK),e.later(100,this,function(){t.one(r.EDITTIMEOFVIDEO).focus()}),e.one("input[name=timeofvideo")||e.one("body").append('<input type="text" name="timeofvideo" style="display: none">')},update_page_break:function(t,n,r,i){t.preventDefault(),nextactivity=r.next("li.activity.slot");var s=this.add_spinner(nextactivity),o=0,u=i==="removepagebreak"?1:2,a={"class":"resource",field:"updatepagebreak",id:o,value:u};return o=e.Moodle.mod_quizinvideo.util.slot.getId(nextactivity),o&&(a.id=Number(o)),this.send_request(a,s,function(t){if(t.slots){if(i==="addpagebreak")e.Moodle.mod_quizinvideo.util.page.add(r);else{var n=r.next(e.Moodle.mod_quizinvideo.util.page.SELECTORS.PAGE);e.Moodle.mod_quizinvideo.util.page.remove(n,!0)}this.reorganise_edit_page()}else window.location.reload(!0)}),this},reorganise_edit_page:function(){e.Moodle.mod_quizinvideo.util.slot.reorderSlots(),e.Moodle.mod_quizinvideo.util.slot.reorderPageBreaks(),e.Moodle.mod_quizinvideo.util.page.reorderPages(),this.get_time_from_db_for_all_pages()},get_time_from_db_for_all_pages:function(){},NAME:"mod_quizinvideo-resource-toolbox",ATTRS:{courseid:{value:0},quizinvideoid:{value:0}}}),M.mod_quizinvideo.resource_toolbox=null,M.mod_quizinvideo.init_resource_toolbox=function(e){return M.mod_quizinvideo.resource_toolbox=new o(e),M.mod_quizinvideo.resource_toolbox};var u=function(){u.superclass.constructor.apply(this,arguments)};e.extend(u,s,{initializer:function(){M.mod_quizinvideo.quizinvideobase.register_module(this),e.delegate("click",this.toggle_highlight,r.PAGECONTENT,r.SECTIONLI+" "+r.HIGHLIGHT,this),e.delegate("click",this.toggle_hide_section,r.PAGECONTENT,r.SECTIONLI+" "+r.SHOWHIDE,this)},toggle_hide_section:function(t){t.preventDefault();var i=t.target.ancestor(M.mod_quizinvideo.format.get_section_selector(e)),s=t.target.ancestor("a",!0),o=s.one("img"),u,a,f;i.hasClass(n.SECTIONHIDDENCLASS)?(i.removeClass(n.SECTIONHIDDENCLASS),u=1,a="show",f="hide"):(i.addClass(n.SECTIONHIDDENCLASS),u=0,a="hide",f="show");var l=M.util.get_string(f+"fromothers","format_"+this.get("format"));o.setAttrs({alt:l,src:M.util.image_url("i/"+f)}),s.set("title",l);var c={"class":"section",field:"visible",id:e.Moodle.core_course.util.section.getId(i.ancestor(M.mod_quizinvideo.edit.get_section_wrapper(e),!0)),value:u},h=M.util.add_lightbox(e,i);h.show(),this.send_request(c,h,function(t){var n=i.all(r.ACTIVITYLI);n.each(function(n){var i;n.one(r.SHOW)?i=n.one(r.SHOW):i=n.one(r.HIDE);var s=e.Moodle.mod_quizinvideo.util.slot.getId(n);e.Array.indexOf(t.resourcestotoggle
,""+s)!==-1&&M.mod_quizinvideo.resource_toolbox.handle_resource_dim(i,n,a)},this)})},toggle_highlight:function(t){t.preventDefault();var n=t.target.ancestor(M.mod_quizinvideo.edit.get_section_selector(e)),i=t.target.ancestor("a",!0),s=i.one("img"),o=n.hasClass("current"),u=0,a=M.util.get_string("markthistopic","moodle");e.one(r.PAGECONTENT).all(M.mod_quizinvideo.edit.get_section_selector(e)+".current "+r.HIGHLIGHT).set("title",a),e.one(r.PAGECONTENT).all(M.mod_quizinvideo.edit.get_section_selector(e)+".current "+r.HIGHLIGHT+" img").set("alt",a).set("src",M.util.image_url("i/marker")),e.one(r.PAGECONTENT).all(M.mod_quizinvideo.edit.get_section_selector(e)).removeClass("current");if(!o){n.addClass("current"),u=e.Moodle.core_course.util.section.getId(n.ancestor(M.mod_quizinvideo.edit.get_section_wrapper(e),!0));var f=M.util.get_string("markedthistopic","moodle");i.set("title",f),s.set("alt",f).set("src",M.util.image_url("i/marked"))}var l={"class":"course",field:"marker",value:u},c=M.util.add_lightbox(e,n);c.show(),this.send_request(l,c)}},{NAME:"mod_quizinvideo-section-toolbox",ATTRS:{courseid:{value:0},quizinvideoid:{value:0},format:{value:"topics"}}}),M.mod_quizinvideo.init_section_toolbox=function(e){return new u(e)}},"@VERSION@",{requires:["base","node","event","event-key","io","moodle-mod_quizinvideo-quizinvideobase","moodle-mod_quizinvideo-util-slot","moodle-core-notification-ajaxexception"]});
