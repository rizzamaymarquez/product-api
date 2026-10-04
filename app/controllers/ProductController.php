<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
    }

    // GET /api/products
    public function index()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $products = $this->db->raw(
            "SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['data' => $products]);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $product = $this->find_product($id);
        $this->api->respond(['data' => $product]);
    }

    // POST /api/products
    public function store()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();

        $data = $this->validate_product($this->api->body());

        $this->db->raw(
            "INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)",
            [$data['product_name'], $data['description'], $data['price'], $data['quantity']]
        );

        $new_id = $this->db->raw("SELECT LAST_INSERT_ID() AS id")->fetch(PDO::FETCH_ASSOC)['id'];

        $this->api->respond([
            'message' => 'Product created',
            'data'    => $this->find_product($new_id)
        ], 201);
    }

    // PUT or PATCH /api/products/{id}
    public function update($id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'])) {
            $this->api->respond_error('Method Not Allowed', 405);
        }
        $this->api->require_jwt();

        $this->find_product($id);
        $data = $this->validate_product($this->api->body());

        $this->db->raw(
            "UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?",
            [$data['product_name'], $data['description'], $data['price'], $data['quantity'], $id]
        );

        $this->api->respond([
            'message' => 'Product updated',
            'data'    => $this->find_product($id)
        ]);
    }

    // DELETE /api/products/{id}
    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->api->require_jwt();

        $this->find_product($id);
        $this->db->raw("DELETE FROM products WHERE id = ?", [$id]);

        $this->api->respond(['message' => 'Product deleted']);
    }

    // ---- helpers ----

    private function find_product($id)
    {
        $product = $this->db->raw(
            "SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1",
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $this->api->respond_error('Product not found', 404);
        }

        return $product;
    }

    private function validate_product($input)
    {
        $name     = trim($input['product_name'] ?? '');
        $desc     = $input['description'] ?? null;
        $price    = $input['price'] ?? '';
        $quantity = $input['quantity'] ?? '';

        $errors = [];
        if ($name === '' || strlen($name) > 100) {
            $errors['product_name'] = 'Product name is required (max 100 characters).';
        }
        if (!is_numeric($price) || $price < 0) {
            $errors['price'] = 'Price must be a number that is 0 or higher.';
        }
        if (filter_var($quantity, FILTER_VALIDATE_INT) === false || $quantity < 0) {
            $errors['quantity'] = 'Quantity must be a whole number that is 0 or higher.';
        }
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        return [
            'product_name' => $name,
            'description'  => $desc,
            'price'        => $price,
            'quantity'     => (int) $quantity,
        ];
    }
}