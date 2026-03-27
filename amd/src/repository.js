/**
 * @module local_coursedateshiftpro/repository
 * @copyright 2026 Jesus Antonio Jimenez Aviña <antoniomexdf@gmail.com> <antoniojamx@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    /**
     * Calls the Moodle external service that returns the rendered preview.
     *
     * @param {Object} payload
     * @returns {Promise}
     */
    const getPreview = function(payload) {
        return Ajax.call([{
            methodname: 'local_coursedateshiftpro_get_preview',
            args: payload,
        }])[0];
    };

    return {
        getPreview: getPreview,
    };
});
