var _typeof = typeof Symbol === "function" && typeof Symbol.iterator === "symbol" ? function(obj) {
    return typeof obj;
} : function(obj) {
    return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj;
};

var SizeAttrs = ['height', 'width', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'top', 'right', 'bottom', 'left', 'lineHeight', 'fontSize'];
var DashedSizeAttrs = SizeAttrs.map(function(attr) {
    return camelCase2Dash(attr);
});

function camelCase2Dash(str) {
    return str.replace(/([a-zA-Z])(?=[A-Z])/g, '$1-').toLowerCase();
}

function getUnitizedValue(value) {
    if (/^\d+(\.\d+)?$/.test(value)) {
        return value + 'px';
    } else {
        return value;
    }
}

function getPlainStyle(target) {
    if (!target) {
        return '';
    }
    var style = '';
    var type = typeof target === 'undefined' ? 'undefined' : _typeof(target);
    if (type === 'string') {
        style = target;
    } else if (type === 'object') {
        var dashAttr = '';
        Object.keys(target).forEach(function(attr) {
            dashAttr = camelCase2Dash(attr);

            if (target[attr]) {
                if (DashedSizeAttrs.indexOf(dashAttr) > -1 || SizeAttrs.indexOf(attr) > -1) {
                    style += dashAttr + ': ' + getUnitizedValue(target[attr]) + ';';
                } else {
                    style += dashAttr + ': ' + target[attr] + ';';
                }
            }
        });
    }
    return style;
}

Component({
    behaviors: [],
    properties: {
        customStyle: {
            type: Object | String
        },
        title: {
            type: String
        }
    },
	options: {
		multipleSlots: true
	},
    ready: function ready() {
        var height = wx.STATUS_BAR_HEIGHT + wx.DEFAULT_HEADER_HEIGHT;
        var style = this.data.customStyle || {};
        style.height = height;
        style.paddingTop = wx.STATUS_BAR_HEIGHT;
        this.setData({
            statusBarHeight: wx.STATUS_BAR_HEIGHT,
            selfCustomStyle: getPlainStyle(style)
        });
    }
});