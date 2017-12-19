<?php declare(strict_types = 1);

namespace Longman\TelegramBot\Commands\UserCommands;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;


/**
 * User "/time" command.
 */
class TimeCommand extends UserCommand
{
    protected $name = 'time';
    protected $description = 'Converts time.';
    protected $usage = '/time <time string>';
    protected $version = '1.0.0';
    private $client;

    /**
     * Gets a timezone by longitude and latitude using the Google Maps
     * timezone API.
     * https://developers.google.com/maps/documentation/timezone
     */
    protected function getTimeZoneByCoords(float $latitude, float $longitude, \Carbon\Carbon $time) : string
    {
        $url = 'timezone/json';
        $query = [
            'location' => $latitude . ',' . $longitude,
            'timestamp' => $time->format('U'),
            'key' => $this->getConfig('google_api_key')
        ];

        try {
            $response = $this->client->get($url, ['query' => $query]);
        } catch (RequestException $e) {
            TelegramLog::error($e->getMessage());
            return '';
        }

        $gresponse = json_decode($response->getBody()->getContents(), true);
        if (count($gresponse) >= 1) {
            return $gresponse['timeZoneId'];
        }
        return '';
    }

    /**
     * Gets a timezone by city name using nominatim
     * https://nominatim.openstreetmap.org to query for latitude and
     * longitude and getTimeZoneByCoords to get the time zone from there.
     */
    protected function getTimezoneByCity(string $city, \Carbon\Carbon $time) : string
    {
        $url_city = trim($city);

        $url = 'geocode/json';
        $query = [
            'address' => $url_city,
            'key' => $this->getConfig('google_api_key')
        ];

        try {
            $response = $this->client->get($url, ['query' => $query]);
        } catch (RequestException $e) {
            TelegramLog::error($e->getMessage());
            return '';
        }
        $response = $response->getBody()->getContents();
        $response = json_decode($response, true);
        if (count($response['results']) >= 1) {
            $latitude = $response['results'][0]['geometry']['location']['lat'];
            $longitude = $response['results'][0]['geometry']['location']['lng'];
            return $this->getTimeZoneByCoords($latitude, $longitude, $time);
        } else {
            return '';
        }
    }

    /**
     * Parses a message and determines the requested time.
     */
    protected function parse_message(string $message) : string
    {
        // TODO(shoeffner): Currently assumes local server time to be the
        //                  user's time. Change this!
        date_default_timezone_set('Europe/Berlin');

        $time_and_city = explode('in', $message);
        $time = $time_and_city[0];

        try {
            // Try parsing date
            $parsed = Carbon::parse($time);
        } catch (\Exception $e) {
            // Assume city name only
            $timezone = $this->getTimezoneByCity($time, Carbon::now());
            if (!empty($timezone)) {
                $parsed = Carbon::now($timezone);
            } else {
                return 'Can not find a timezone for ' . $time . ', sorry!';
            }
        }

        if (count($time_and_city) > 1) {
            $city = $time_and_city[1];
            $timezone = $this->getTimezoneByCity($city, $parsed);
            if (!empty($timezone)) {
                $parsed = $parsed->timezone($timezone);
            } else {
                return 'Could not find ' . $city . ', sorry!';
            }
        }

        return $parsed->format(\DateTime::COOKIE);
    }

    /**
     * @inheritdoc
     */
    public function execute() : ServerResponse
    {
        $this->client = new Client(['base_uri' => 'https://maps.googleapis.com/maps/api/']);

        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = trim($message->getText(true));

        if ($message->getReplyToMessage() != null) {
            $reply = $message->getReplyToMessage()->getText();
            $parsed_message = $this->parse_message($reply);
        } else {
            $parsed_message = $this->parse_message($text);
        }

        $data = [
            'chat_id' => $chat_id,
            'text' => $parsed_message,
        ];

        return Request::sendMessage($data);
    }
}
