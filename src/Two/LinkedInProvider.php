<?php

namespace Laravel\Socialite\Two;

use Illuminate\Support\Arr;

class LinkedInProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['r_liteprofile', 'r_basicprofile', 'r_emailaddress'];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * The fields that are included in the profile.
     *
     * @var array
     */
    protected $fields = [
        'id', 'first-name', 'last-name', 'formatted-name',
        'email-address', 'headline', 'location', 'industry',
        'public-profile-url', 'picture-url', 'picture-urls::(original)',
    ];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://www.linkedin.com/oauth/v2/authorization', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenFields($code)
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $basicProfile = $this->getBasicProfile($token);
        $emailAddress = $this->getEmailAddress($token);

        return array_merge($basicProfile, $emailAddress);
    }
    /**
     * Get the basic profile fields for the user.
     *
     * @param  string  $token
     * @return array
     */
    protected function getBasicProfile($token)
    {
        $url = 'https://api.linkedin.com/v2/me?projection=(id,firstName,lastName,profilePicture(displayImage~:playableStreams))';

        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
        ]);

        return (array) json_decode($response->getBody(), true);
    }
    /**
     * Get the email address for the user.
     *
     * @param  string  $token
     * @return array
     */
    protected function getEmailAddress($token)
    {
        $url = 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))';

        $response = $this->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'X-RestLi-Protocol-Version' => '2.0.0',
            ],
        ]);

        return (array) Arr::get((array) json_decode($response->getBody(), true), 'elements.0.handle~');
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $preferredLocale = Arr::get($user, 'firstName.preferredLocale.language').'_'.Arr::get($user, 'firstName.preferredLocale.country');
        $firstName = Arr::get($user, 'firstName.localized.'.$preferredLocale);
        $lastName = Arr::get($user, 'lastName.localized.'.$preferredLocale);

        $headline = Arr::get($user, 'headline.localized.'.$preferredLocale);

        $images = (array) Arr::get($user, 'profilePicture.displayImage~.elements', []);
        $avatar = Arr::first(Arr::where($images, function ($image) {
            return $image['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['width'] === 100;
        }), function() {return true;});
        $originalAvatar = Arr::first(Arr::where($images, function ($image) {
            return $image['data']['com.linkedin.digitalmedia.mediaartifact.StillImage']['storageSize']['width'] === 800;
        }), function() {return true;});

        return (new User)->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => null,
            'name' => $firstName.' '.$lastName,
            'headline' => $headline,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'vanityName' => Arr::get($user, 'vanityName'),
            'email' => Arr::get($user, 'emailAddress'),
            'avatar' => Arr::get($avatar, 'identifiers.0.identifier'),
            'avatar_original' => Arr::get($originalAvatar, 'identifiers.0.identifier'),
        ]);
        
        /*
        return (new User)->setRaw($user)->map([
            'id' => $user['id'], 'nickname' => null, 'name' => Arr::get($user, 'formattedName'),
            'email' => Arr::get($user, 'emailAddress'), 'avatar' => Arr::get($user, 'pictureUrl'),
            'avatar_original' => Arr::get($user, 'pictureUrls.values.0'),
        ]);
        */
    }

    /**
     * Set the user fields to request from LinkedIn.
     *
     * @param  array  $fields
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->fields = $fields;

        return $this;
    }
}
