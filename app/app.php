<?php
/**
 * Local variables
 * @var \Phalcon\Mvc\Micro $app
 */

/*
 * * * * * * * * * * * * * * * *
 * Common pages                *
 * * * * * * * * * * * * * * * *
*/

/**
 * Initial page
 */
$app->get('/', function () use ($app) {
    echo $this['view']->render('index', array(
        'baseurl' => '/GEBEM/'
    ));
});

/**
 * Not-Found page
 */
$app->notFound(function () use ($app) {
    echo json_encode(
        array("GEBEM_ERROR" =>
            array(
                "code" => "400",
                "reasonPhrase" => "Bad Request",
                "details" => "Route not found"
            )
        )
    );
});

/*
 * * * * * * * * * * * * * * * *
 * API v1                      *
 * * * * * * * * * * * * * * * *
*/

/**
 * Registration route
 */
$app->post('/v1/registration', function () use ($app) {
    $user = new Usuario();

    $user->name = $app->request->getPost('name', 'string');
    $user->email = $app->request->getPost('email', 'email');
    $user->username = $app->request->getPost('username', 'alphanum');
    $user->password = $app->request->getPost('password', 'string');
    $cpassword = $app->request->getPost('cpassword', 'string');

    if(strlen($user->name) < 4 ||
        strlen($user->username) < 4 ||
        strlen($user->password) < 6){

        echo $this['view']->render('index', array(
            'warning' => 'warning',
            'message' => 'Name, username or password is invalid.'
            )
        );
        return;
    }

    if(strcmp($user->password, $cpassword) != 0){
        echo $this['view']->render('index', array(
                'warning' => 'warning',
                'message' => 'Password confirmation is wrong.'
            )
        );
        return;
    }

    $user->password = $this->security->hash($user->password);

    if(!$user->save()){

        $errorMessage = '';
        foreach ($user->getMessages() as $message){
            $errorMessage .= $message.'<br>';
        }

        echo $this['view']->render('index', array(
                'warning' => 'warning',
                'message' => 'Something went wrong.<br>'.$errorMessage
            )
        );
        return;
    }

    echo $this['view']->render('index', array(
            'success' => 'success',
            'message' => 'User saved.You may use the API now :) .<br>Use /token [POST] with the username and password to get a token to access the API.'
        )
    );
    return;
});
