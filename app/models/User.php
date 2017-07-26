<?php

namespace GEBEM\Models;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\StringLength as StringLengthValidator;
use Phalcon\Mvc\Model as Model;

class User extends Model
{

    /**
     *
     * @var integer
     */
    public $idusuario;

    /**
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var string
     */
    public $email;

    /**
     *
     * @var string
     */
    public $username;

    /**
     *
     * @var string
     */
    public $password;

    /**
     * Validations and business logic
     *
     * @return boolean
     */
    public function validation()
    {
        $validator = new Validation();
        $validator->add(
            'name',
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'Please enter a name.'
            ])
        );
        $validator->add(
            'email',
            new EmailValidator([
                'model' => $this,
                'message' => 'Please enter a correct email address.'
            ])
        );
        $validator->add(
            'email',
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'Please enter an email.'
            ])
        );
        $validator->add(
            'username',
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'Please enter an username.'
            ])
        );
        $validator->add(
            'password',
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'Please enter a password.'
            ])
        );
        $validator->add(
            ["name", "email", "username"],
            new StringLengthValidator([
                "max" => [
                    "name"  => 45,
                    "email" => 256,
                    "username"  => 45
                ],
                "min" => [
                    "name"  => 4,
                    "email" => 4,
                    "username"  => 4
                ],
                "messageMaximum" => [
                    "name"  => "Name must have a maximum of 45 characters",
                    "email" => "Email must have a maximum of 256 characters",
                    "username"  => "Username must have a maximum of 45 characters"
                ],
                "messageMinimum" => [
                    "name"  => "Name must have a minimum of 4 characters",
                    "email" => "Name must have a minimum of 4 characters",
                    "username"  => "Name must have a minimum of 4 characters"
                ]
            ])
        );
        return $this->validate($validator);
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'user';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return User[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return User
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
