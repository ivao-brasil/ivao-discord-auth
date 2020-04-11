<html>
<title>{{str_replace('_',' ', env('DEFAULT_TITLE'))}}</title>
<body>
<div class="success-message">
    <img alt="teste" id="img" src="{{asset('assets/imgs/houston-we-have-a-problem.jpeg')}}"/>
    <h1 class="success-message__title">{{$text}}</h1>
</div>
</body>
</html>

<style>
    html {
        font-family: Verdana;
    }

    #img {
        border-radius:  25px;
    }

    .success-message {
        text-align: center;
        min-width: 50%;
        max-width: 90%;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    @media(max-width: 450px){
        .success-message__icon {
            width: 450px;
        }
    }

    @media(min-width: 451px){
        .success-message__icon {
            width: 75px;
        }
    }

    .success-message__title {
        color: #ff3333;
        transform: translateY(25px);
        opacity: 0;
        transition: all 200ms ease;
        font-size: 4vh;
        font-family: 'Nunito', sans-serif;
    }
    .active .success-message__title {
        transform: translateY(0);
        opacity: 1;
    }

    .success-message__content {
        color: #B8BABB;
        transform: translateY(25px);
        opacity: 0;
        transition: all 200ms ease;
        transition-delay: 50ms;
    }
    .active .success-message__content {
        transform: translateY(0);
        opacity: 1;
    }

    .icon-checkmark circle {
        fill: #7289DA;
        transform-origin: 50% 50%;
        transform: scale(0);
        transition: transform 200ms cubic-bezier(0.22, 0.96, 0.38, 0.98);
    }
    .icon-checkmark path {
        transition: stroke-dashoffset 350ms ease;
        transition-delay: 100ms;
    }
    .active .icon-checkmark circle {
        transform: scale(1);
    }
</style>

<script>
    function PathLoader(el) {
        this.el = el;
        this.strokeLength = el.getTotalLength();

        // set dash offset to 0
        this.el.style.strokeDasharray =
            this.el.style.strokeDashoffset = this.strokeLength;
    }

    PathLoader.prototype._draw = function (val) {
        this.el.style.strokeDashoffset = this.strokeLength * (1 - val);
    }

    PathLoader.prototype.setProgress = function (val, cb) {
        this._draw(val);
        if(cb && typeof cb === 'function') cb();
    }

    PathLoader.prototype.setProgressFn = function (fn) {
        if(typeof fn === 'function') fn(this);
    }

    var body = document.body,
        svg = document.querySelector('svg path');

    if(svg !== null) {
        svg = new PathLoader(svg);

        setTimeout(function () {
            document.body.classList.add('active');
            svg.setProgress(1);
        }, 200);
    }

    window.addEventListener('load', function() {
        document.body.classList.add('active');
        svg.setProgress(1);
    });

    document.addEventListener('click', function () {

        if(document.body.classList.contains('active')) {
            document.body.classList.remove('active');
            svg.setProgress(0);
            return;
        }
        document.body.classList.add('active');
        svg.setProgress(1);
    });
</script>
