# Notification

Notification for Laravel projects

## Installation

The recommended method to install LaravelRepository is with composer

```bash
php composer require mblsolutions/notification
```
### Laravel without auto-discovery

If you don't use auto-discovery, add the ServiceProvider to the providers array in config/app.php

```php
\MBLSolutions\Notification\NotificationServiceProvider::class,
```

### Package configuration

```php
Copy the package configuration to your local config directory.
```

```bash
php artisan vendor:publish --tag=notification-config
```

### Database Driver

If you would like to use the Database driver to store your notification logs, you will first need to create and run the database
driver migration.

```bash
php artisan notification:database:table
```

This will create a new migration in `database/migrations`, after creating this migration run the database migrations to
create the new table.

````bash
php artisan migrate
````

## Usage

The configuration and setup can be adjusted in the notification config file located in `config/notification.php`. We 
recommend reading through the config file before enabling notification to ensure you have the optimum setup. 

### Enable Notification Service

In environment setting, you need to change MAIL_MAILER from smtp to paypoint to enable the service. The endpoint and credentials is neede to add in your .env file.

```dotenv
MAIL_MAILER=paypoint
PP_NOTIFICATION_endpoint=https://ENDPOINT_URL
PP_NOTIFICATION_SUBSCRIPTION_KEY=xxxxxxxxxxx
```

### Template Setup

The template should be generated manually via the endpoint and an existing template id that is needed. If any field in request body that needs to validate before sending it to endpoint, a rule needs to specify for that template.

```php
'template' => [
        'default_id' => env('TEMPLATE_ID','xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxx1'),
    ],
```

This can also be set in your .env file by using the corresponding environment variable

```dotenv
TEMPLATE_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxx1
```

### Notification Setup

In application, you need to use or replace a new PayPointMailMessage which are wrapped the value of 'X-Template-Request-Body' and 'X-Template-Id'.

```php
class SendEmail extends Notification implements ShouldQueue
{
    //... construct method

    public function toMail($notifiable)
    {
        return (new PayPointMailMessage(
                    //Change the template id if not default
                    config('notification.template.default_id'),
                    //Request Body
                    [   
                        'CallbackUrl' => '',
                        'Model' =>
                            [
                            'NotificationModel' =>
                                [
                                    'Recipients' =>
                                    [
                                        [
                                            'Name' => 'Test Customer',
                                            'Email' => 'test@test.com'
                                        ]
                                    ],
                                    /* .. */
                                ]
                            ]                 
                        ]
                ));
    }
}
```

### Enable user information for logging in job

If you would like to have user information in notification logs, you need to set user in authentication as in the example below:

```php
class SendEmail extends Notification implements ShouldQueue
{
    //... construct method

    public function toMail($notifiable)
    {
        return (new PayPointMailMessage(/* .. */))
                ->withUser($notifiable);
    }
}
```

### Optional validation for request body

If any field in request body that needs to validate before sending it to endpoint, a rule needs to add in notification.php config file as in the example below:

```php
'template' => [
        //Optional validation array with template id as key
        //The rule depends on what is the matched validation field in array from the above example of notification setup. e.g. $data
        'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxx1' => [
            'validation_rule' => [
                'Model.NotificationModel.Recipients.*.Name' => ['required', 'string', 'max:255'],
                'Model.NotificationModel.Recipients.*.Email' => ['required', 'email', 'max:255'],
            ],
        ]
    ],
```

## License

Notification is free software distributed under the terms of the MIT license.