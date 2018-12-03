module.exports = Behavior({

    data: {
        sliderBarWrapStyle: ''
    },
    methods: {
        sliderBarWrap2DynamicStyleObj: function sliderBarWrap2DynamicStyleObj() {
            var style = {};
            style['z-index'] = this.data.zIndex2;
            style['transform'] = 'translate3d(' + this.data.offset2X + 'px, 0, 0)';
			style.height = this.data.thumbSize + 'px';
			style.width = this.data.thumbSize + 'px';
            return style;
        }
    }
});