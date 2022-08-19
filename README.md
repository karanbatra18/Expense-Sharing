## About Expense Sharing App
An expense sharing application is where you can add your expenses and split it among different people. The app keeps balances between people as in who owes how much to whom.
Expense sharing app is built into Laravel framework to provide api support for the following scenarios:

1. User: Each user should have a userId, name, email, mobile number.
2. Expense: Could either be EQUAL, EXACT or PERCENT
3. Users can add any amount, select any type of expense and split with any of the available
   users.
4. The percent and amount provided could have decimals upto two decimal places.
5. In case of percent, you need to verify if the total sum of percentage shares is 100 or not.
6. In case of exact, you need to verify if the total sum of shares is equal to the total amount
   or not.
7. The application should have a capability to show expenses for a single user as well as
   balances for everyone.
8. When asked to show balances, the application should show balances of a user with all the
   users where there is a non-zero balance.
9. The amount should be rounded off to two decimal places.

## Project Installation steps

Take repository clone on your system
Add env file with your DB credentials
Run the following commands:

Composer install
php artisan migrate --seed
php artisan passport:install

## API End points provided

## Auth
/api/register
Example request with Payload: https://prnt.sc/n2dT2u2v8kpV

/api/login
Example request with Payload: https://prnt.sc/a9AHPw9OAvSk

## Expense supported End points with required authorization

#1. Save Expenses
End Point: /api/expenses
Method: POST
Headers: Authorization
Example request with Payload: https://prnt.sc/BvW0HiAxk14F

#2. Show Expenses 
End Point: /api/show/expenses
Method: GET
Headers: Authorization

#3 Show Balances
End Point: /api/show/balances
Method: GET
Headers: Authorization
Example request with Payload: https://prnt.sc/NfnqAKvMANqM

#4 Return supported Expenses types
End Point: api/expense_types
Method: GET
Headers: Authorization
