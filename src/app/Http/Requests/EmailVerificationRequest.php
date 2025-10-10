<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Http\FormRequest;

class EmailVerificationRequest extends FormRequest
{
    protected $unauthenticated_user;

    protected $guard;

    public function __construct(StatefulGuard $guard)
    {
        $this->unauthenticated_user = session()->get('unauthenticated_user');
        $this->guard = $guard;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (! $this->unauthenticated_user) {
            return false;
        }
        if (! hash_equals(
            (string) $this->unauthenticated_user->getKey(),
            (string) $this->route('id')
        )) {
            return false;
        }

        if (! hash_equals(
            sha1($this->unauthenticated_user->getEmailForVerification()),
            (string) $this->route('hash')
        )) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }

    public function fulfill()
    {
        if (! $this->unauthenticated_user->hasVerifiedEmail()) {
            $this->unauthenticated_user->markEmailAsVerified();

            $this->guard->login($this->unauthenticated_user);
        }
    }

    public function withValidator($validator)
    {
        return $validator;
    }
}
