<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>IVAO-BR Credencial Discord</title>
        <script src="https://code.jquery.com/jquery-3.4.1.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
    </head>
    <body>
        <div id="container">
            <div id="inviteContainer">
                <div class="logoContainer"><img class="logo" src="https://seeklogo.com/images/D/discord-logo-134E148657-seeklogo.com.png" /><img class="text" src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/ca/Discord_Color_Text_Logo.svg/2000px-Discord_Color_Text_Logo.svg.png" /></div>
                <div class="acceptContainer">
                    <form>
                        <h1>Bem-vindo, {{$firstName}}</h1>
                        <div class="formContainer">
                            <div class="formDiv">
                                <p>Por favor, use o botão abaixo para autenticar usando a sua conta do Discord e ingressar no nosso Servidor</p>
                            </div>
                            <div class="formDiv">
                                <button class="acceptBtn" type="button" onclick="window.location.href='{{ url('auth/discord') }}'">Login</button>
                                <span class="register">Ainda não tem uma conta?<a href="https://discordapp.com/register" target="_blank">Crie agora!</a></span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>

<style>
    @import url("https://fonts.googleapis.com/css?family=Montserrat:400,600,700|Work+Sans:300,400,700,900");
    * {
        outline-width: 0;
        font-family: 'Montserrat' !important;
    }

    body {
        background-repeat:repeat-x;
        margin: 0;
    }
    #container {
        height: 100vh;
        background-size: cover !important;
        display: -webkit-box;
        display: flex;
        -webkit-box-pack: center;
        justify-content: center;
        -webkit-box-align: center;
        align-items: center;
    }

    @media(max-width: 450px) {
        #inviteContainer {
            display: -webkit-box;
            display: flex;
            overflow: hidden;
            position: relative;
            border-radius: 5px;
            width: 90%;
        }
    }

    @media (min-width: 451px){
        #inviteContainer {
            display: -webkit-box;
            display: flex;
            overflow: hidden;
            position: relative;
            border-radius: 5px;
        }
    }
    #inviteContainer .acceptContainer {
        padding: 45px 30px;
        box-sizing: border-box;
        width: 400px;
    }
    #inviteContainer .acceptContainer:before {
        content: "";
        background-size: cover !important;
        box-shadow: inset 0 0 0 3000px rgba(40, 43, 48, 0.75);
        -webkit-filter: blur(10px);
        filter: blur(10px);
        position: absolute;
        width: 150%;
        height: 150%;
        top: -50px;
        left: -50px;
    }

    form {
        position: relative;
        text-align: center;
        height: 100%;
    }
    form h1 {
        margin: 0 0 15px 0;
        font-family: 'Work Sans' !important;
        font-weight: 700;
        font-size: 20px;
        color: #fff;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    .formContainer {
        text-align: left;
    }
    .formContainer .formDiv {
        margin-bottom: 30px;
    }
    .formContainer .formDiv:last-child {
        padding-top: 10px;
        margin-bottom: 0;
    }
    .formContainer p {
        margin: 0;
        font-weight: 700;
        color: #aaa;
        font-size: 10px;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    .formContainer input[type=password], .formContainer input[type=email] {
        background: transparent;
        border: none;
        box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.15);
        padding: 15px 0;
        box-sizing: border-box;
        color: #fff;
        width: 100%;
        -webkit-transition: 0.2s ease;
        transition: 0.2s ease;
    }
    .formContainer input[type=password]:focus, .formContainer input[type=email]:focus {
        box-shadow: inset 0 -2px 0 #fff;
    }

    @media(max-width: 650px) {
        .logoContainer {
            display: none;
        }
    }

    @media(min-width: 651px) {
        .logoContainer {
            padding: 45px 35px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            position: relative;
            overflow: hidden;
            display: -webkit-box;
            display: flex;
            -webkit-box-align: center;
            align-items: center;
            -webkit-box-pack: center;
            justify-content: center;
            -webkit-box-orient: vertical;
            -webkit-box-direction: normal;
            flex-direction: column;
        }
        .logoContainer img {
            width: 150px;
            margin-bottom: -5px;
            display: block;
            position: relative;
        }
        .logoContainer img:first-child {
            width: 150px;
        }
        .logoContainer .text {
            padding: 25px 0 10px 0;
        }
        .logoContainer:before {
            content: "";
            background-size: cover !important;
            position: absolute;
            top: -50px;
            left: -50px;
            width: 150%;
            height: 150%;
            -webkit-filter: blur(10px);
            filter: blur(10px);
            box-shadow: inset 0 0 0 3000px rgba(255, 255, 255, 0.8);
        }
    }

    .forgotPas {
        color: #aaa;
        opacity: .8;
        text-decoration: none;
        font-weight: 700;
        font-size: 10px;
        margin-top: 15px;
        display: block;
        -webkit-transition: 0.2s ease;
        transition: 0.2s ease;
    }
    .forgotPas:hover {
        opacity: 1;
        color: #fff;
    }

    .acceptBtn {
        width: 100%;
        box-sizing: border-box;
        background: #7289DA;
        border: none;
        color: #fff;
        padding: 20px 0;
        border-radius: 3px;
        cursor: pointer;
        -webkit-transition: 0.2s ease;
        transition: 0.2s ease;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    .acceptBtn:hover {
        background: #6B7FC5;
    }

    .register {
        color: #aaa;
        font-size: 12px;
        padding-top: 15px;
        display: block;
    }
    .register a {
        color: #fff;
        text-decoration: none;
        margin-left: 5px;
        box-shadow: inset 0 -2px 0 transparent;
        padding-bottom: 5px;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    .register a:hover {
        box-shadow: inset 0 -2px 0 #fff;
    }
</style>

<script>
    var images = [
        "https://live.staticflickr.com/65535/48823757581_05d752ffb9_k.jpg",
        "https://live.staticflickr.com/4335/36646294165_1f95482881_k.jpg",
        "https://live.staticflickr.com/4058/34810343034_da4c49b42c_k.jpg",
        "https://live.staticflickr.com/4240/35343459622_f864cdc597_k.jpg",
        "https://live.staticflickr.com/882/41766871754_388a2032b1_h.jpg",
        "https://live.staticflickr.com/945/41807112872_60bf2bec53_h.jpg"
    ]


    $('#container').append('<style>#container, .acceptContainer:before, .logoContainer:before {background: url(' + images[Math.floor(Math.random() * images.length)] + ') center fixed }');

    setInterval(() => {
        $('#container').append('<style>#container, .acceptContainer:before, .logoContainer:before {background: url(' + images[Math.floor(Math.random() * images.length)] + ') center fixed }');
    },10000)
</script>
