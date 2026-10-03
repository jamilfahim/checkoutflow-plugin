/** DOM type checks also support elements adopted by Elementor from its parent frame. */
(function () {
	'use strict';
	window.eilmoCfDom = {
		isElement: function (value, tagName) {
			return !!value && value.nodeType === 1 && (!tagName || value.tagName === tagName);
		},
	};
})();
