<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Models\Balance;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Split;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;

class ExpenseController extends BaseController
{
    /**
     * Store a newly created Expense in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $input = $request->all();

        /* Expense validation Rules */
        $rules = [
            'description' => 'required',
            'amount' => 'required|regex:/^(([0-9]*)(\.([0-9]{0,2}+))?)$/',
            'paid_by' => 'required',
            'expense_type_id' => 'required',
            'split_users.*.email' => 'required',
            'split_users.*.value' => 'regex:/^(([0-9]*)(\.([0-9]{0,2}+))?)$/'
        ];

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Setting up variables
        $expenseTypeId = $input['expense_type_id'];
        $splitUsers = $input['split_users'];
        $amount = $input['amount'];
        $input['user_id'] = $user->id;
        $paidById = $input['paid_by'];

        // validate the total exact share sum in case of Exact
        if ($expenseTypeId == 2) {
            $totalExactSum = 0;
            if (count($splitUsers)) {
                foreach ($splitUsers as $splitUser) {
                    $totalExactSum += $splitUser['value'];
                }
            }

            if ($totalExactSum != $amount) {
                $exactError = [
                    'Split Percentage' => 'Total sum of shares should be equal to the total amount'
                ];
                return $this->sendError('Validation Error.', $exactError);
            }
        }

        // validate the total percentage sum in case of PERCENT
        if ($expenseTypeId == 3) {
            $totalPercentageSum = 0;
            if (count($splitUsers)) {
                foreach ($splitUsers as $splitUser) {
                    $totalPercentageSum += $splitUser['value'];
                }
            }

            if ($totalPercentageSum != 100) {
                $percentageError = [
                    'Split Percentage' => 'Total sum of percentage shares should be 100'
                ];
                return $this->sendError('Validation Error.', $percentageError);
            }
        }

        // Save Expense if all good
        $expense = Expense::create($input);

        // Execute the Split Calculation and save in DB
        $this->splitExpenses($amount, $expenseTypeId, $paidById, $splitUsers, $expense);

        return $this->sendResponse($expense, 'Expense created successfully.');
    }

    /**
     * return all expenses of a user
     *
     * @return \Illuminate\Http\Response
     */
    public function showExpenses()
    {
        $user = auth()->user();

        // Get Expense Id of user where expenses paid by the request user
        $splitExpenses = Split::where('user_id', $user->id)->pluck('expense_id')->toArray();

        // Return all expenses of the user
        $expenses = Expense::whereIn('id', $splitExpenses)->get()->toArray();

        return $this->sendResponse($expenses, 'Expenses returned successfully.');
    }

    /**
     * return all balances for the requested user
     *
     * @return \Illuminate\Http\Response
     */
    public function showBalances()
    {
        $balanceArray = [];
        $user = auth()->user();

        // Get all non-zero balances paid by the requested user
        $balances = Balance::with('paidByUser')->where('paid_to', $user->id)->where('amount', '!=', 0)->get();

        if ($balances->count()) {
            foreach ($balances as $balance) {
                if ($balance->amount > 0) {
                    $balanceArray[] = $balance->paidByUser->name . ' owes you: ' . abs($balance->amount);
                } else {
                    $balanceArray[] = 'You owes ' . $balance->paidByUser->name . ': ' . abs($balance->amount);
                }
            }
        }

        // Get all non-zero balances owe by the requested user
        $reverseBalances = Balance::with('paidToUser')->where('paid_by', $user->id)->where('amount', '!=', 0)->get();
        if ($reverseBalances->count()) {
            foreach ($reverseBalances as $reverseBalance) {
                if ($reverseBalance->amount < 0) {
                    $balanceArray[] = $reverseBalance->paidToUser->name . ' owes you: ' . abs($reverseBalance->amount);
                } else {
                    $balanceArray[] = 'You owes ' . $reverseBalance->paidToUser->name . ': ' . abs($reverseBalance->amount);
                }
            }
        }

        return $this->sendResponse($balanceArray, 'Balances returned successfully.');
    }

    /**
     * Function to update the balce on DB with respect to current expense
     *
     * @param $paidTo
     * @param $paidBy
     * @param $amount
     * @return bool
     */
    public function updateBalances($paidTo, $paidBy, $amount)
    {
        // Check if the balance entry present in DB for the combination of users
        $balance = Balance::where('paid_to', $paidTo)->where('paid_by', $paidBy)->first();

        if (!empty($balance)) {
            $balance->amount += $amount;
            $balance->save();
        } else {
            // Check if the balance entry present in DB for the combination of users in reverse order
            $balanceReverse = Balance::where('paid_to', $paidBy)->where('paid_by', $paidTo)->first();

            if (!empty($balanceReverse)) {
                $balanceReverse->amount -= $amount;
                $balanceReverse->save();
            } else {

                // If not already exist than create a new balance record
                $data = [
                    'paid_to' => $paidTo,
                    'paid_by' => $paidBy,
                    'amount' => $amount
                ];
                Balance::create($data);
            }
        }

        return true;
    }

    /**
     * @param $amount
     * @param $expenseTypeId
     * @param $paidById
     * @param $splitUsers
     * @param $expense
     * @return bool
     */
    public function splitExpenses($amount, $expenseTypeId, $paidById, $splitUsers, $expense) {
        $firstUser = null;
        $totalDividedSum = 0;
        switch ($expenseTypeId) {
            case('1'): // EQUAL Scenario
                $usersCount = count($splitUsers);
                $oneUserShare = $amount / $usersCount;
                $oneUserShare = floor($oneUserShare * 100) / 100;

                foreach ($splitUsers as $splitUser) {
                    $checkUser = User::where('email', $splitUser['email'])->first();
                    $participatedUser = null;

                    if ($checkUser) {
                        $participatedUser = $checkUser;
                    } else {
                        $userData = [
                            'email' => $splitUser['email'],
                        ];
                        if (!empty($splitUser['name'])) {
                            $userData['name'] = $splitUser['name'];
                        }
                        $participatedUser = User::create($userData);
                    }

                    if ($paidById != $participatedUser->id) {
                        $totalDividedSum += $oneUserShare;
                        if (empty($firstUser)) {
                            $firstUser = $participatedUser;
                        }
                        $splitArray = [
                            'user_id' => $paidById,
                            'owe_user_id' => $participatedUser->id,
                            'amount' => $oneUserShare ?? 0
                        ];
                        $this->updateBalances($paidById, $participatedUser->id, $oneUserShare);
                        $expense->splits()->create($splitArray);
                    } else {
                        $totalDividedSum += $oneUserShare;
                    }
                }
                break;

            case('2'): // EXACT Scenario
                foreach ($splitUsers as $splitUser) {
                    $oneUserShare = $splitUser['value'] ?? 0;

                    $checkUser = User::where('email', $splitUser['email'])->first();
                    $participatedUser = null;
                    if ($checkUser) {
                        $participatedUser = $checkUser;
                    } else {
                        $participatedUser = User::create([
                            'email' => $splitUser['email']
                        ]);
                    }
                    if ($paidById != $participatedUser->id) {
                        $totalDividedSum += $oneUserShare;
                        if (empty($firstUser)) {
                            $firstUser = $participatedUser;
                        }
                        $splitArray = [
                            'user_id' => $paidById,
                            'owe_user_id' => $participatedUser->id,
                            'amount' => $oneUserShare ?? 0,
                            'value' => 0
                        ];
                        $this->updateBalances($paidById, $participatedUser->id, $oneUserShare);
                        $expense->splits()->create($splitArray);
                    } else {
                        $totalDividedSum += $oneUserShare;
                    }

                }
                break;

            case('3'): // PERCENT Scenario
                foreach ($splitUsers as $splitUser) {
                    $percentValue = $splitUser['value'];
                    $oneUserShare = ($amount * $percentValue) / 100;
                    $oneUserShare = floor($oneUserShare * 100) / 100;

                    $checkUser = User::where('email', $splitUser['email'])->first();

                    $participatedUser = null;
                    if ($checkUser) {
                        $participatedUser = $checkUser;
                    } else {
                        $participatedUser = User::create([
                            'email' => $splitUser['email']
                        ]);
                    }

                    if ($paidById != $participatedUser->id) {
                        $splitArray = [
                            'user_id' => $paidById,
                            'owe_user_id' => $participatedUser->id,
                            'amount' => $oneUserShare ?? 0,
                            'value' => $percentValue ?? 0
                        ];
                        if (empty($firstUser)) {
                            $firstUser = $participatedUser;
                        }
                        $this->updateBalances($paidById, $participatedUser->id, $oneUserShare);
                        $totalDividedSum += $oneUserShare;
                    } else {
                        $splitArray = [
                            'user_id' => $paidById,
                            'owe_user_id' => $participatedUser->id,
                            'amount' => 0,
                            'value' => $percentValue ?? 0
                        ];
                        $totalDividedSum += $oneUserShare;
                    }

                    $expense->splits()->create($splitArray);
                }
                break;
        }

        // Add the remaining point balance to first user to match the total amount
        if ($totalDividedSum != $amount) {
            $remainingBalance = $amount - $totalDividedSum;
            $splitData = Split::where('expense_id', $expense->id)->where('owe_user_id', $firstUser->id)->first();
            $this->updateBalances($paidById, $firstUser->id, $remainingBalance);
            $splitData->amount = $splitData->amount + $remainingBalance;
            $splitData->save();
        }

        return true;
    }

    public function expenseTypes() {
        $expenseTypes = ExpenseType::get()->toArray();
        return $this->sendResponse($expenseTypes, 'Expense types returned successfully.');
    }
}
