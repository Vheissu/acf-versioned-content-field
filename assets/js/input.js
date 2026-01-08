(function ($) {
    'use strict';

    var FIELD_TYPE = 'versioned_content';
    var data = window.acfVersionedContentField || {};
    var i18n = data.i18n || {};

    function setNoteIfEmpty($note, text) {
        if (!$note.length) {
            return;
        }

        if ($note.val().trim() === '') {
            $note.val(text);
        }
    }

    function setupField($field) {
        var $textarea = $field.find('[data-role="current-content"]');
        var $note = $field.find('[data-role="change-note"]');

        $field.on('click', '[data-action="restore-version"]', function () {
            var $version = $(this).closest('[data-version]');
            var $content = $version.find('[data-role="version-content"]');
            var content = $content.val() || '';
            var summary = $version.find('[data-role="version-meta"]').data('summary') || '';
            var baseNote = i18n.restoredNote || 'Restored previous version';
            var note = summary ? baseNote + ': ' + summary : baseNote;

            $textarea.val(content).trigger('change');
            setNoteIfEmpty($note, note);
        });
    }

    if (typeof acf !== 'undefined') {
        acf.add_action('ready_field/type=' + FIELD_TYPE, setupField);
        acf.add_action('append_field/type=' + FIELD_TYPE, setupField);
    }
})(jQuery);
