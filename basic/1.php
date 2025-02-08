<?php declare(strict_types = 1);

class UsersBuilder
{
	public function buildUser(int $id, string $name, string $birthday): array
	{
		$result = [
			'ID' => $id,
			'Name' => $name,
			'BirthDay' => new DateTimeImmutable($birthday),
		];

		return $result;
	}

	public function fetchUsers(): array
	{
		// 仮実装なので仮データを返す
		$users = [];
		$users[] = $this->buildUser(1, 'Miku', '2007-08-31');
		$users[] = $this->buildUser(2, 'Rin', '2007-12-27');
		$users[] = $this->buildUser(3, 'Len', '2007-12-27');
		$users[] = $this->buildUser(4, 'Luka', '2009-01-30');

		return $users;
	}
}
