<?php

use Kanboard\Plugin\Harbor\Controller\SubtaskStatusController;

?>
<?php if (!empty($header)): ?>
    <th scope="col" aria-label="<?= $this->text->e(t('Subtask actions')) ?>"></th>
<?php else: ?>
    <td>
        <?= $this->kit->actions('subtask-row', [
            'status' => array_search((int) $subtask['status'], SubtaskStatusController::STATUSES, true),
            'subtask_id' => (int) $subtask['id'],
            'task_id' => (int) $subtask['task_id'],
            'project_id' => (int) $projectId,
        ]) ?>
        <button type="button" data-slot="subtask-actions-trigger" aria-label="<?= $this->text->e(t('Subtask actions')) ?>" aria-haspopup="menu" aria-expanded="false" hidden>⋯</button>
    </td>
<?php endif ?>
