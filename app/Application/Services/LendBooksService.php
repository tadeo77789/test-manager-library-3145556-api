<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\BookChange;
use App\Application\Errors\ConcurrentUpdate;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Domain\Errors\BookNotAvailable;
use App\Domain\Errors\DuplicateBookInLoan;
use App\Domain\Errors\MemberHasOverdue;
use App\Domain\Errors\MemberLimitExceeded;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationFailed;
use App\Domain\Loan;

/** HU-06: register a loan of 1 to 3 books for one member. Nothing is saved unless everything succeeds. */
final class LendBooksService
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
        private readonly IdGenerator $ids,
    ) {
    }

    /** @param list<string> $bookIds */
    public function execute(string $memberId, array $bookIds, string $loanedByUserId): Loan
    {
        $count = count($bookIds);
        if ($count < 1 || $count > Loan::MAX_ITEMS) {
            throw new ValidationFailed('Un préstamo debe tener entre 1 y 3 libros.', [['field' => 'items', 'issue' => 'se requieren de 1 a 3 elementos']]);
        }
        if (count(array_unique($bookIds)) !== $count) {
            throw new DuplicateBookInLoan('No se puede repetir el mismo libro en un préstamo.', [['field' => 'items', 'issue' => 'libro repetido']]);
        }
        if ($this->members->findById($memberId) === null) {
            throw new NotFound('El socio no existe.');
        }

        $now = $this->clock->now();
        if ($this->loans->hasOverdueByMember($memberId, $now)) {
            throw new MemberHasOverdue();
        }
        $current = $this->loans->countUnreturnedByMember($memberId);
        if ($current + $count > Loan::MAX_ITEMS) {
            throw new MemberLimitExceeded(
                sprintf('El socio ya tiene %d libro(s) sin devolver y no puede superar 3.', $current),
                [['field' => 'items', 'issue' => sprintf('sin devolver actualmente: %d', $current)]],
            );
        }

        for ($attempt = 1; ; $attempt++) {
            $found = $this->books->findActiveByIds($bookIds);
            $loan = Loan::open($this->ids->next(), $memberId, $loanedByUserId, $now);
            $changes = [];
            foreach ($bookIds as $index => $bookId) {
                $book = $found[$bookId] ?? null;
                if ($book === null) {
                    throw new NotFound('El libro no existe.', [['field' => sprintf('items[%d].book_id', $index), 'issue' => 'no existe']]);
                }
                try {
                    $added = $loan->addItem($book, $this->ids->next());
                } catch (BookNotAvailable $e) {
                    throw new BookNotAvailable($e->getMessage(), [['field' => sprintf('items[%d].book_id', $index), 'issue' => 'sin ejemplares']]);
                }
                $loan = $added->loan;
                $changes[] = new BookChange($book, $added->book);
            }

            try {
                $this->loans->create($loan, $changes);
                return $loan;
            } catch (ConcurrentUpdate) {
                // Someone else touched a book; re-read and re-evaluate. The final answer is 409 if no copy is left.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new BookNotAvailable('No se pudo reservar el ejemplar por una operación simultánea; intente de nuevo.');
                }
            }
        }
    }
}
