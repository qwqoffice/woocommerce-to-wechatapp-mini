module.exports = Behavior({

    data: {},
    methods: {
        sliderBarAnimateStyleObj: function sliderBarAnimateStyleObj() {
            var style = {};
            style['transition'] = this.data.animate;
            return style;
        },
        sliderBarWrapStyleObj: function sliderBarWrapStyleObj() {
            var style = {};
            style.top = this.data.height / 2 - this.data.thumbSize / 2 + 'px';
            style.left = this.data.valueAlignLeft - this.data.thumbSize / 2 + 'px';
            return style;
        },
        sliderBarStyleObj: function sliderBarStyleObj() {
            var style = {};
            style.height = this.data.thumbSize + 'px';
            style.width = this.data.thumbSize + 'px';
			style['background-color'] = this.data.activeColor;
            return style;
        }
    }
});