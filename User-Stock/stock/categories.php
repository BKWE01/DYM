<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaire détaillé - DYM STOCK</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/antd@4.16.13/dist/antd.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/react@17.0.2/umd/react.development.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@17.0.2/umd/react-dom.development.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/antd@4.16.13/dist/antd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@babel/standalone@7.14.7/babel.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main content -->
        <div id="main-content" class="flex-1 flex flex-col">
            <?php include_once 'header.php'; ?>

            <main class="p-4 flex-1">
                <div class="bg-white p-4 rounded-lg shadow">
                    <h1 class="text-3xl font-bold mb-6">Création de Catégories de produits</h1>
                    <div id="categories-form"></div>
                </div>
            </main>
        </div>
    </div>

    <script type="text/babel">
        const { Form, Input, Button, message, Table, Divider } = antd;

        const CategoriesForm = () => {
            const [form] = Form.useForm();
            const [categories, setCategories] = React.useState([]);
            const [existingCategories, setExistingCategories] = React.useState([]);

            React.useEffect(() => {
                fetchExistingCategories();
            }, []);

            const fetchExistingCategories = () => {
                fetch('api_getCategoriesList.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            setExistingCategories(data.categories);
                        } else {
                            message.error('Erreur lors de la récupération des catégories existantes');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        message.error('Une erreur est survenue lors de la récupération des catégories');
                    });
            };

            const onFinish = (values) => {
                const newCategory = {
                    ...values,
                    key: Date.now(),
                };
                setCategories([...categories, newCategory]);
                form.resetFields();
            };

            const saveCategories = () => {
                fetch('save_categories.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(categories),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        message.success('Catégories enregistrées avec succès');
                        setCategories([]);
                    } else {
                        message.error('Erreur lors de l\'enregistrement des catégories');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    message.error('Une erreur est survenue');
                });
            };

            const columns = [
                {
                    title: 'Libellé',
                    dataIndex: 'libelle',
                    key: 'libelle',
                },
                {
                    title: 'Code',
                    dataIndex: 'code',
                    key: 'code',
                },
            ];

            return (
                <div>
                    <Form form={form} name="category_form" onFinish={onFinish} layout="inline">
                        <Form.Item
                            name="libelle"
                            rules={[{ required: true, message: 'Veuillez entrer le libellé' }]}
                        >
                            <Input placeholder="Libellé" />
                        </Form.Item>
                        <Form.Item
                            name="code"
                            rules={[{ required: true, message: 'Veuillez entrer le code' }]}
                        >
                            <Input placeholder="Code" />
                        </Form.Item>
                        <Form.Item>
                            <Button type="primary" htmlType="submit">
                                Ajouter
                            </Button>
                        </Form.Item>
                    </Form>

                    <Table dataSource={categories} columns={columns} className="mt-4" />

                    <Button
                        type="primary"
                        onClick={saveCategories}
                        disabled={categories.length === 0}
                        className="mt-4"
                    >
                        Enregistrer les catégories
                    </Button>

                    <Divider>Catégories existantes</Divider>

                    <Table dataSource={existingCategories} columns={columns} className="mt-4" />
                </div>
            );
        };

        ReactDOM.render(<CategoriesForm />, document.getElementById('categories-form'));
    </script>
</body>
</html>
