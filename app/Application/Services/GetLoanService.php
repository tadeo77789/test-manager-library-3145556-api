<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\LoanRepository;
use App\Domain\Errors\NotFound;
use App\Domain\Loan;

final class GetLoanService
{
    public function __construct(private readonly LoanRepository $loans)
    {
    }

    public function execute(string $id): Loan
    {
        return $this->loans->findById($id) ?? throw new NotFound('El préstamo no existe.');
    }
}
