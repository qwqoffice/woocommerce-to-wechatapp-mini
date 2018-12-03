module.exports = Behavior({

    data: {
        sliderBarWrapStyle: ''
    },
    methods: {
        sliderBarWrapDynamicStyleObj: function sliderBarWrapDynamicStyleObj() {
            var style = {};
            style['z-index'] = this.data.zIndex;
            style['transform'] = 'translate3d(' + this.data.offsetX + 'px, 0, 0)';
            style.height = this.data.thumbSize + 'px';
            style.width = this.data.thumbSize + 'px';
            return style;
        }
    }
});