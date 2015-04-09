YUI.add("moodle-mod_quizinvideo-util-page",function(e,t){e.namespace("Moodle.mod_quizinvideo.util.page"),e.Moodle.mod_quizinvideo.util.page={CSS:{PAGE:"page"},CONSTANTS:{ACTIONMENUIDPREFIX:"action-menu-",ACTIONMENUBARIDSUFFIX:"-menubar",ACTIONMENUMENUIDSUFFIX:"-menu",PAGEIDPREFIX:"page-",PAGENUMBERPREFIX:M.util.get_string("page","moodle")+" "},SELECTORS:{ACTIONMENU:"div.moodle-actionmenu",ACTIONMENUBAR:"ul.menubar",ACTIONMENUMENU:"ul.menu",PAGE:"li.page",INSTANCENAME:".instancename",NUMBER:"span.text"},getPageFromComponent:function(t){return e.one(t).ancestor(this.SELECTORS.PAGE,!0)},getPageFromSlot:function(t){return e.one(t).previous(this.SELECTORS.PAGE)},getId:function(e){var t=e.get("id").replace(this.CONSTANTS.PAGEIDPREFIX,"");return t=parseInt(t,10),typeof t=="number"&&isFinite(t)?t:!1},setId:function(e,t){e.set("id",this.CONSTANTS.PAGEIDPREFIX+t)},getName:function(e){var t=e.one(this.SELECTORS.INSTANCENAME);return t?t.get("firstChild").get("data"):null},getNumber:function(e){var t=e.one(this.SELECTORS.NUMBER).get("text").replace(this.CONSTANTS.PAGENUMBERPREFIX,"");return t=parseInt(t,10),typeof t=="number"&&isFinite(t)?t:!1},setNumber:function(e,t){e.one(this.SELECTORS.NUMBER).set("text",this.CONSTANTS.PAGENUMBERPREFIX+t)},getPages:function(){return e.all(e.Moodle.mod_quizinvideo.util.slot.SELECTORS.PAGECONTENT+" "+e.Moodle.mod_quizinvideo.util.slot.SELECTORS.SECTIONUL+" "+this.SELECTORS.PAGE)},isPage:function(e){return e?e.hasClass(this.CSS.PAGE):!1},isEmpty:function(e){var t=e.next("li.activity");return t?!t.hasClass("slot"):!0},add:function(t){var n=this.getNumber(this.getPageFromSlot(t))+1,r=M.mod_quizinvideo.resource_toolbox.get("config").pagehtml;r=r.replace(/%%PAGENUMBER%%/g,n);var i=e.Node.create(r);return YUI().use("dd-drop",function(e){var t=new e.DD.Drop({node:i,groups:M.mod_quizinvideo.dragres.groups});i.drop=t}),t.insert(i,"after"),M.core.actionmenu.newDOMNode(i),i},remove:function(t,n){var r=t.previous(e.Moodle.mod_quizinvideo.util.slot.SELECTORS.SLOT);!n&&r&&e.Moodle.mod_quizinvideo.util.slot.removePageBreak(r),t.remove()},reorderPages:function(){var e=this.getPages(),t=0;e.each(function(e){if(this.isEmpty(e)){var n=e.next("li.slot")?!0:!1;this.remove(e,n);return}t++,this.setNumber(e,t),this.setId(e,t)},this),this.reorderActionMenus()},reorderActionMenus:function(){var e=this.getActionMenus();e.each(function(t,n){var r=e.item(n-1);previousActionMenunumber=0,r&&(previousActionMenunumber=this.getActionMenuId(r));var i=previousActionMenunumber+1;this.setActionMenuId(t,i);var s=t.one(this.SELECTORS.ACTIONMENUBAR);s.set("id",this.CONSTANTS.ACTIONMENUIDPREFIX+i+this.CONSTANTS.ACTIONMENUBARIDSUFFIX);var o=t.one(this.SELECTORS.ACTIONMENUMENU);o.set("id",this.CONSTANTS.ACTIONMENUIDPREFIX+i+this.CONSTANTS.ACTIONMENUMENUIDSUFFIX)},this)},getActionMenus:function(){return e.all(e.Moodle.mod_quizinvideo.util.slot.SELECTORS.PAGECONTENT+" "+e.Moodle.mod_quizinvideo.util.slot.SELECTORS.SECTIONUL+" "+this.SELECTORS.ACTIONMENU)},getActionMenuId:function(e){var t=e.get("id").replace(this.CONSTANTS.ACTIONMENUIDPREFIX,"");return t=parseInt(t,10),typeof t=="number"&&isFinite(t)?t:!1},setActionMenuId:function(e,t){e.set("id",this.CONSTANTS.ACTIONMENUIDPREFIX+t)}}},"@VERSION@",{requires:["node","moodle-mod_quizinvideo-util-base"]});
