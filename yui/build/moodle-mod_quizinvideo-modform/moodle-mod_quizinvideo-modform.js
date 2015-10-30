YUI.add('moodle-mod_quizinvideo-modform', function (Y, NAME) {

/**
 * The modform class has all the JavaScript specific to mod/quizinvideo/mod_form.php.
 *
 * @module moodle-mod_quizinvideo-modform
 */

var MODFORM = function() {
    MODFORM.superclass.constructor.apply(this, arguments);
};

/**
 * The coursebase class to provide shared functionality to Modules within
 * Moodle.
 *
 * @class M.course.coursebase
 * @constructor
 */
Y.extend(MODFORM, Y.Base, {
    repaginateCheckbox: null,
    qppSelect: null,
    qppInitialValue: 0,

    initializer: function () {
        this.repaginateCheckbox = Y.one('#id_repaginatenow');
        if (!this.repaginateCheckbox) {
            // The checkbox only appears when editing an existing quizinvideo.
            return;
        }

        this.qppSelect = Y.one('#id_questionsperpage');
        this.qppInitialValue = this.qppSelect.get('value');
        this.qppSelect.on('change', this.qppChanged, this);
    },

    qppChanged: function() {
        Y.later(50, this, function() {
            if (!this.repaginateCheckbox.get('disabled')) {
                this.repaginateCheckbox.set('checked', this.qppSelect.get('value') !== this.qppInitialValue);
            }
        });
    }

});

// Ensure that M.mod_quizinvideo exists and that coursebase is initialised correctly
M.mod_quizinvideo = M.mod_quizinvideo || {};
M.mod_quizinvideo.modform = M.mod_quizinvideo.modform || new MODFORM();
M.mod_quizinvideo.modform.init = function() {
    return new MODFORM();
};


}, '@VERSION@', {"requires": ["base", "node", "event"]});
