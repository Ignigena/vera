<?php
/**
 * @file Implements VeraTaxonomy.
 */

class VeraTaxonomy {

  /**
   * Implements helper function to create vocabulary and populate with terms.
   *
   * @param object $vocabulary
   *   The vocabulary to be created.
   * @param array $terms
   *   An array of terms to create.
   */
  public function createVocabularyAndPopulate(&$vocabulary, $terms) {
    // Create the taxonomy vocabulary.
    taxonomy_vocabulary_save($vocabulary);
    
    // Populate the vocabulary with terms.
    $this->term_create_bulk($vocabulary->vid, $terms);
  }

  /**
   * Implements helper function to create single taxonomy term.
   *
   * @param string $name
   *   The name of the taxonomy term to create.
   * @param integer $vid
   *   The vocabulary ID in which to create the new term.
   * @param integer $parent optional
   *   The parent taxonomy term ID.
   *
   * @return integer
   *   The term ID of the newly created taxonomy term.
   */
  public function term_create($name, $vid, $parent = 0) {
    $term = new stdClass();
    $term->name = $name;
    $term->vid = $vid;
    $term->parent = $parent;
    taxonomy_term_save($term);
    return $term->tid;
  }

  /**
   * Implements helper to create taxonomy term with children.
   *
   * @param string $name
   *   The name of the taxonomy term to create.
   * @param integer $vid
   *   The vocabulary ID in which to create the new term.
   * @param array $children
   *   An array of children to associate with the new term.
   */
  public function term_create_with_children($name, $vid, $children) {
    // Create the parent term first.
    $parent = $this->term_create($name, $vid);

    // Create each of the children with the new parent term.
    foreach ($children as $child) {
      $this->term_create($child, $vid, $parent);
    }

    return $parent;
  }

  /**
   * Implements bulk taxonomy term creation.
   *
   * @param integer $vid
   *   The common vocabulary ID for all terms.
   * @param array $terms
   *   An array of terms to create.
   * @param integer $parent optional
   *   The parent taxonomy term ID.
   */
  function term_create_bulk($vid, $terms, $parent = 0) {
    foreach ($terms as $term) {
      if (is_array($term)) {
        $term_name = key($term);
        $children = $term[$term_name];
        $this->term_create_with_children($term_name, $vid, $children);
      } else {
        $this->term_create($term, $vid, $parent);
      }
    }
  }
}
