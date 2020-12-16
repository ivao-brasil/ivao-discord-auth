<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{str_replace('_',' ', env('DEFAULT_TITLE'))}}</title>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"
            integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

</head>
<body>
<div id="consentModal" class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><b>Declaration of consent for the use of the Division
                        Discord</b></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="consentText">
                    <p>We use the Discord platform for the purpose of holding video and
                        audio conferences, trainings, exams, webinars and other types of
                        video and audio meetings. When selecting third-party providers and
                        their services, we observe the legal requirements.</p>

                    <p>In this context, data of communication participants will be processed
                        and stored on the servers of third party providers, as far as they are
                        part of communication processes with us. This data may include, in
                        particular, registration and contact data, visual and vocal
                        contributions, as well as entries in chats and shared screen content</p>

                    <p>
                        <u>References to legal bases:</u>
                    </p>
                    <p>
                        The use is based on consent and constitutes the legal basis of the
                        processing. Should further data be required, the user's consent is
                        required separately.
                    </p>

                    <p>
                        An (automatic) deletion of the collected data will take place after the
                        deletion of the IVAO account.
                    </p>

                    <ul>
                        <li>
                            <b>Processed data types</b>: first name, vid, content data (e.g. entries in
                            online forms), usage data (e.g. websites visited, interest in content,
                            access times), meta/communication data (e.g. device information,
                            IP addresses)
                        </li>

                        <li>
                            <b>Persons concerned</b>: Users of online services (such as training)
                        </li>

                        <li>
                            <b>Purposes of the processing</b>: provision of services, contact
                                requests and communication, daily administration, direct
                                marketing
                        </li>

                        <li>
                            <b>Legal basis</b>: Consent (Art. 6 para. 1 sentence 1 lit. a. GDPR)
                        </li>
                    </ul>

                    <p>
                        <u>Right of withdrawal</u>
                    </p>

                    <p>
                        The undersigned has the right to withdraw this consent at any time
                        with effect for the future without giving reasons. An e-mail to [e-mail
                        address] is sufficient for this purpose. The lawfulness of the
                        processing carried out on the basis of the consent until the withdrawal
                        is not affected by the withdrawal.
                    </p>

                    <p><u>Consequences of not signing</u></p>

                    <p>The undersigned has the right to disagree with this declaration of
                        consent - however, since our service depends on the collection and
                        processing of the data mentioned at the beginning, non-signature
                        would preclude the use of the service.</p>

                    <p>Discord: Instant Messaging, chat, voice and video conferencing; service
                        provider:: Discord, Inc., 444 De Haro St, Suite 200, San Francisco,
                        California 94107, USA; Website: https://discordapp.com/; Privacy
                        policy: https://discordapp.com/privacy.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div id="container">
    <div id="inviteContainer">
        <div class="logoContainer"><img class="logo"
                                        src="https://seeklogo.com/images/D/discord-logo-134E148657-seeklogo.com.png"/><img
                class="text"
                src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/ca/Discord_Color_Text_Logo.svg/2000px-Discord_Color_Text_Logo.svg.png"/>
        </div>
        <div class="acceptContainer">
            <form>
                <h1>@lang('text.welcome'), {{$firstName}}</h1>
                <div class="formContainer">
                    <div class="formDiv">
                        <p>@lang('text.authInstruction')</p>
                    </div>
                    <div class="formDiv">
                        <button class="acceptBtn" type="button" id="loginButton"
                                onclick="window.location.href='{{ url('discord/login') }}'"
                                disabled>@lang('text.loginBtn')</button>
                        <span class="register">
                           <input type="checkbox" id="checkboxConsent"/>
                           @lang('text.consentmentDeclaration1')
                              <b class="link" id="consent">@lang('text.consent')</b>
                           @lang('text.consentmentDeclaration2')

                              <b class="link" id="privacy">@lang('text.privacyPolicy')</b>
                        </span>
                        <span class="register">
                            @lang('text.noAccount')
                            <a href="https://discordapp.com/register" target="_blank">@lang('text.createAccount')</a>
                        </span>
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

    .consentText {
        text-align: justify;
        text-justify: inter-word;
    }

    * {
        outline-width: 0;
        font-family: 'Montserrat' !important;
    }

    body {
        background-repeat: repeat-x;
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

    @media (max-width: 450px) {
        #inviteContainer {
            display: -webkit-box;
            display: flex;
            overflow: hidden;
            position: relative;
            border-radius: 5px;
            width: 90%;
        }
    }

    @media (min-width: 451px) {
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

    @media (max-width: 650px) {
        .logoContainer {
            display: none;
        }
    }

    @media (min-width: 651px) {
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

    .link {
        cursor: pointer;
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
    }, 10000)

    document.getElementById('checkboxConsent').addEventListener('change', function (event) {
        if (event.target.checked) {
            document.getElementById('loginButton').removeAttribute('disabled')
        } else {
            document.getElementById('loginButton').setAttribute('disabled', true)
        }
    })

    document.getElementById('consent').addEventListener('click', function() {
        $('#consentModal').modal()
    })

    document.getElementById('privacy').addEventListener('click', function() {
        window.open('https://wiki.ivao.aero/en/home/ivao/privacypolicy')
    })
</script>
