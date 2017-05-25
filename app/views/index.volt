<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
        <title>GEBEM</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
        <script src="https://use.fontawesome.com/ca2e1aeda5.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    </head>
    <body>
    <div class="container">
        <div class="row main">
            <div class="panel-heading">
                <div class="panel-title text-center">
                    <h1 class="title">
                        <a href="/GEBEM/" style="color: black;text-decoration: none !important;">GEBEM</a>
                    </h1>
                    <p>Generic Enabler for Buildings Energy Managment</p>
                </div>
                {% if success is defined %}
                    <div class="alert alert-success col-lg-12 col-md-12 col-sm-12 col-xs-12" role="alert">
                        <strong>Success!</strong> {{ message }}
                    </div>
                {% endif %}

                {% if error is defined %}
                    <div class="alert alert-danger col-lg-12 col-md-12 col-sm-12 col-xs-12" role="alert">
                        <strong>Error!</strong> {{ message }}
                    </div>
                {% endif %}

                {% if warning is defined %}
                    <div class="alert alert-warning col-lg-12 col-md-12 col-sm-12 col-xs-12" role="alert">
                        <strong>Warning!</strong> {{ message }}
                    </div>
                {% endif %}
            </div>


            <div class="panel-heading" >
                <div class="panel-title text-center">
                    <p style="margin-top: 25px">API v1 registration</p>
                </div>
            </div>
            <div class="main-login main-center" style="width: 50%;margin: auto">
                <form class="form-horizontal" method="post" action="/GEBEM/v1/form/registration">

                    <div class="form-group">
                        <label for="name" class="cols-sm-2 control-label">Your Name</label>
                        <div class="cols-sm-10">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-user fa" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" name="name" id="name"  placeholder="Enter your Name" minlength="4" maxlength="45" required/>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="cols-sm-2 control-label">Your Email</label>
                        <div class="cols-sm-10">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-envelope fa" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" name="email" id="email"  placeholder="Enter your Email" minlength="4" maxlength="255" required/>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username" class="cols-sm-2 control-label">Username</label>
                        <div class="cols-sm-10">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-users fa" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" name="username" id="username"  placeholder="Enter your Username" minlength="4" maxlength="45" required/>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="cols-sm-2 control-label">Password</label>
                        <div class="cols-sm-10">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-lock fa-lg" aria-hidden="true"></i></span>
                                <input type="password" class="form-control" name="password" id="password"  placeholder="Enter your Password" minlength="6" maxlength="60" required/>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm" class="cols-sm-2 control-label">Confirm Password</label>
                        <div class="cols-sm-10">
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-lock fa-lg" aria-hidden="true"></i></span>
                                <input type="password" class="form-control" name="cpassword" id="cpassword"  placeholder="Confirm your Password" minlength="6" maxlength="60" required/>
                            </div>
                        </div>
                    </div>

                    <div class="form-group ">
                        <button type="submit" class="btn btn-primary btn-lg btn-block login-button">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        </div>
        <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
        <!-- Latest compiled and minified JavaScript -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    </body>
</html>
