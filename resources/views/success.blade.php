<html>

    <body>
        <div class="success-message">
            <svg viewBox="0 0 76 76" class="success-message__icon icon-checkmark">
                <circle cx="38" cy="38" r="36"/>
                <path fill="none" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" d="M17.7,40.9l10.9,10.9l28.7-28.7"/>
            </svg>
            <h1 class="success-message__title">Permissão Concedida</h1>
            <div class="success-message__content">
                <p>Basta acessar o nosso servidor e interagir!</p>
            </div>
        </div>
    </body>
</html>

<style>
    html {
        font-family: Verdana;
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
        color: #7289DA;
        transform: translateY(25px);
        opacity: 0;
        transition: all 200ms ease;
        font-size: 4vh;
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
