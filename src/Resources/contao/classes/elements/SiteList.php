<?php
namespace GyWa\OrganizerBundle;

class SiteList extends \ContentElement
{

	protected $strTemplate = 'ce_sitelist';

    protected function compile()
    {
        if (TL_MODE == 'BE') {
            $this->genBeOutput();
        } else {
            $this->genFeOutput();
        }
    }

    private function genBeOutput()
    {
        $this->strTemplate          = 'be_wildcard';
        $this->Template             = new \BackendTemplate($this->strTemplate);
        $this->Template->title      = $this->headline;
        $this->Template->wildcard   = "### SiteList ###";
    }

    private function genFeOutput()
    {
        $page = $this->Database->prepare("SELECT * FROM tl_page WHERE id=(SELECT tl_article.pid FROM tl_article WHERE tl_article.id=(SELECT tl_content.pid FROM tl_content WHERE tl_content.id = ?))")
            ->limit(1)->execute($this->id);

        $subPages = $this->Database->prepare("SELECT * FROM tl_page WHERE pid=?")->execute($page->id);

        $sortedPages = array();

        if ($subPages->numRows > 0) {
            do {
                if (!empty($subPages->category)) {
                    $category = $this->Database->prepare("SELECT * FROM tl_category WHERE id=?")->limit(1)->execute($subPages->category);
                    if ($category->numRows > 0) {
                        if (!is_array($sortedPages[$category->title])) {
                            $sortedPages[$category->title] = array('css' => $category->cssClass);
                        }
                        array_push($sortedPages[$category->title], array('title' => $subPages->title, 'id' => $subPages->id, 'alias' => $subPages->alias, 'css' => $subPages->cssClass));
                    }
                } else {
                    if (!is_array($sortedPages['default'])) {
                        $sortedPages['default'] = array();
                    }
                    array_push($sortedPages['default'], array('title' => $subPages->title, 'id' => $subPages->id, 'alias' => $subPages->alias));
                }
            } while ($subPages->next());
        }

        $this->Template->arrProperties = $sortedPages;
    }
}