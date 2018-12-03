module.exports = Behavior({
    data: {
        sliderLineTouchStyle: '',
        sliderLineBackgroundStyle: '',
        sliderLineStyle: ''
    },
    methods: {
        touchMoveHandler: function touchMoveHandler(e) {
            // this.
        },
        touchStartHandler: function touchStartHandler(e) {},
        sliderLineStyleObj: function sliderLineStyleObj() {
            var style = {};
            style['background-color'] = this.data.activeColor;
            style.top = this.data.thumbSize / 2 - this.data.sliderWeight / 2 + 'px';
            style.height = this.data.sliderWeight + 'px';
            // style.left = this.data.valueAlignLeft + 'px'
            style['border-radius'] = this.data.barBorderRadius + 'px';
            return style;
        },
        sliderLineBackgroundStyleObj: function sliderLineBackgroundStyleObj() {
            var style = {};
            style['background-color'] = this.data.backgroundColor;
            // style.left = this.data.valueAlignLeft + 'px'
            style.top = this.data.thumbSize / 2 - this.data.sliderWeight / 2 + 'px';
            style.height = this.data.sliderWeight + 'px';
            style['border-radius'] = this.data.barBorderRadius + 'px';
            return style;
        },
        sliderLineTouchStyleObj: function sliderLineTouchStyleObj() {
            var style = {};
            style.left = this.data.valueAlignLeft + 'px';
            style.top = this.data.height / 2 - this.data.thumbSize / 2 + 'px';
            style.height = this.data.thumbSize + 'px';
            style.width = this.data.width - this.data.valueAlignWidth + 'px';
            return style;
        },
        sliderLineDynamicStyleObj: function sliderLineDynamicStyleObj() {
            var style = {};
            style['background-color'] = this.data.activeColor;
            style.width = this.data.offsetX + 'px';
            style.left = this.data.offsetX + 'px';
            style.width = this.data.offset2X - this.data.offsetX + 'px';
            return style;
        }
    }
});