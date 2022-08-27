<?php
namespace Concrete\Controller\SinglePage\Dashboard\Reports\Health;

use Concrete\Core\Entity\Health\Report\Result;
use Concrete\Core\Health\Report\Command\DeleteReportResultCommand;
use Concrete\Core\Health\Report\Finding\CsvWriter;
use Concrete\Core\Navigation\Item\Item;
use Concrete\Core\Navigation\Item\PageItem;
use Concrete\Core\Page\Controller\DashboardPageController;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;

class Details extends DashboardPageController
{

    private function setHealthResultBreadcrumb(Result $result)
    {
        $breadcrumb = $this->createBreadcrumb();
        $items = $breadcrumb->getItems();
        array_pop($items);
        $breadcrumb->setItems($items);
        $breadcrumb->add(new Item('#', $result->getName()));
        $this->setBreadcrumb($breadcrumb);
    }

    protected function getResult($resultId = null): ?Result
    {
        $result = null;
        if ($resultId) {
            $result = $this->entityManager->find(Result::class, $resultId);
        }

        if (!$result) {
            throw new \Exception(t('Invalid result ID.'));
        }

        return $result;
    }

    public function view($resultId = null)
    {
        $result = $this->getResult($resultId);

        // Set breadcrumb
        $this->setHealthResultBreadcrumb($result);

        $findings = $result->getWeightedFindings();
        $this->set('result', $result);

        if (count($findings) > 0) {
            $pagination = new Pagerfanta(new ArrayAdapter($findings));
            $pagination->setMaxPerPage(20);

            if ($this->request->query->has('p')) {
                $currentPage = (int) $this->request->query->get('p');
                $pagination->setCurrentPage($currentPage);
            }

            $manager = $this->app->make('manager/view/pagination');
            $driver = $manager->driver('dashboard');

            $this->set('pagination', $pagination);
            $this->set('paginationView', $driver);

            $showGrade = true;
            if ($pagination->getCurrentPage() > 1) {
                $showGrade = false;
            }

            $this->set('showGrade', $showGrade);

            if (count($findings) > 0) {
                $this->setThemeViewTemplate('full.php');
            }
        }
    }

    public function export($resultId = null, $token = null)
    {
        $result = $this->getResult($resultId);
        if ($this->token->validate('export', $token)) {
            $writer = $this->app->make(CsvWriter::class);
            return $writer->outputResultFindings($result);
        }
        $this->view($resultId);
    }

    public function delete()
    {
        $this->view($this->request->request->get('resultID'));
        if (!$this->token->validate('delete')) {
            $this->error->add($this->token->getErrorMessage());
        }
        if (!$this->error->has()) {
            $result = $this->get('result');

            $command = new DeleteReportResultCommand($result->getId());
            $this->app->executeCommand($command);

            $this->flash('success', t('Result deleted successfully.'));
            return $this->buildRedirect(['/dashboard/reports/health']);
        }
    }



}
