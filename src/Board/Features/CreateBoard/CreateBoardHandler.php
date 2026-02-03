<?php

namespace App\Board\Features\CreateBoard;

use App\Board\Entity\Board;
use App\Board\Entity\Column;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

readonly class CreateBoardHandler
{
    private const FRACTIONAL_STEP = 1000;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function handle(CreateBoardMessage $message, User $owner): BoardCreatedResponse
    {
        $board = new Board($message->title, $owner);

        foreach ($message->columns as $index => $columnInput) {
            $position = ($index + 1) * self::FRACTIONAL_STEP;
            $column = new Column($columnInput->name, (string) $position);
            $board->addColumn($column);
        }

        $this->entityManager->persist($board);
        $this->entityManager->flush();

        return new BoardCreatedResponse(
            $board->getId(),
            $board->getUuid(),
            $board->getTitle()
        );
    }
}
