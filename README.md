# Rate Retrieval Challenge
This project implements a modular and tested solution to interact with the Ship Primus API, managing authentication, token refreshing, and rate processing as required by the technical challenge.

The architecture follows the Service-Controller pattern, separating HTTP request handling from business logic and external API communication.

Requirements:

- PHP >= 8.1

- Composer

- Laravel Framework (assumed for environment setup)

- Guzzle HTTP Client

🚀 Project Setup and Execution
Follow these steps to configure and run the project locally.

# 1. Installation Bash

## Clone the repository
git clone https://github.com/cbalman/rate_retrieval_challenge.git

cd rate-retrieval-challenge

## Install PHP dependencies
composer install

## Copy environment file and generate key (Laravel steps)
cp .env.example .env
php artisan key:generate

# 2. Environment Configuration
   Ensure your .env file contains the following critical environment variables for the Ship Primus Sandbox API:

- SHIPPRIMUS_API_BASE=https://sandbox-api.shipprimus.com/api/v1/
- SHIPPRIMUS_USERNAME=testDemo
- SHIPPRIMUS_PASSWORD=1234
- SHIPPRIMUS_VENDOR_ID=1901539643

# 3. Run the Application
   Start the Laravel development server:

Bash

php artisan serve

The server will be available at http://localhost:8000.

🌐 Rates Endpoint

The main endpoint for retrieving rates is a GET request, requiring all parameters, including the complex freightInfo JSON object, to be passed via the Query String.

Example Request URL

Send a GET request to the following endpoint. Note that the freightInfo array is JSON-encoded and URL-encoded within the query string.

GET http://localhost:8000/api/rates?originCity=KEY LARGO&originState=FL&originZipcode=33037&originCountry=US&destinationCity=LOS ANGELES&destinationState=CA&destinationZipcode=90001&destinationCountry=US&UOM=US&freightInfo=[{"qty":1,"weight":100,"weightType":"each","length":40,"width":40,"height":40,"class":100,"hazmat":0,"commodity":"","dimType":"PLT","stack":false}]
# 🧪 Running Unit Tests

The solution includes a comprehensive suite of Unit Tests to guarantee the reliability of the authentication mechanism and business logic. These tests utilize Mocking for network isolation (Guzzle) and Dependency Injection for effective unit testing.

## Run all tests using the following command:

Bash

php artisan test
