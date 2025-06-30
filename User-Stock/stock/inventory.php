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
                    <h1 class="text-3xl font-bold mb-6">Inventaire détaillé</h1>
                    <div id="inventory-app"></div>
                </div>
            </main>
        </div>
    </div>

    <script type="text/babel">
        const { Table, message, Tooltip, Card, Statistic, Row, Col, Select, DatePicker, Button, Drawer } = antd;
        const { RangePicker } = DatePicker;

        const InventoryApp = () => {
            const [inventoryData, setInventoryData] = React.useState({
                inventory: [],
                totalEntries: 0,
                totalOutputs: 0,
                period: 'month',
                startDate: '',
                endDate: ''
            });
            const [loading, setLoading] = React.useState(true);
            const [drawerVisible, setDrawerVisible] = React.useState(false);
            const [selectedProduct, setSelectedProduct] = React.useState(null);

            React.useEffect(() => {
                fetchInventory(inventoryData.period);
            }, [inventoryData.period]);

            const fetchInventory = async (selectedPeriod) => {
                try {
                    setLoading(true);
                    const response = await fetch(`api_inventory.php?period=${selectedPeriod}`);
                    const data = await response.json();
                    setInventoryData(prevData => ({
                        ...prevData,
                        ...data
                    }));
                    setLoading(false);
                } catch (error) {
                    console.error('Erreur lors de la récupération de l\'inventaire:', error);
                    message.error('Erreur lors de la récupération de l\'inventaire');
                    setLoading(false);
                }
            };

            // Mise à jour des colonnes du tableau React dans inventory.php
            const columns = [
                {
                    title: 'Code-barres',
                    dataIndex: 'barcode',
                    key: 'barcode',
                },
                {
                    title: 'Nom du produit',
                    dataIndex: 'product_name',
                    key: 'product_name',
                },
                {
                    title: 'Quantité actuelle',
                    dataIndex: 'current_quantity',
                    key: 'current_quantity',
                },
                {
                    title: 'Quantité réelle',
                    dataIndex: 'available_quantity',
                    key: 'available_quantity',
                    render: (text, record) => (
                        <span style={{ color: text <= 0 ? 'red' : 'inherit' }}>
                            {text}
                        </span>
                    ),
                },
                {
                    title: 'Catégorie',
                    dataIndex: 'category',
                    key: 'category',
                },
                {
                    title: 'Total entrées',
                    dataIndex: 'total_entries',
                    key: 'total_entries',
                },
                {
                    title: 'Total sorties',
                    dataIndex: 'total_outputs',
                    key: 'total_outputs',
                },
                {
                    title: 'Dates',
                    key: 'dates',
                    render: (text, record) => (
                        <Tooltip title={`Mis à jour le : ${record.updated_at}`}>
                            <span>Créé le : {record.created_at}</span>
                        </Tooltip>
                    ),
                },
                {
                    title: 'Actions',
                    key: 'actions',
                    render: (text, record) => (
                        <span>
                            <Tooltip title="Voir les détails">
                                <Button icon={<i className="fas fa-eye"></i>} onClick={() => handleViewDetails(record.id)} />
                            </Tooltip>
                        </span>
                    ),
                },
            ];
            const handleViewDetails = async (id) => {
                try {
                    const response = await fetch(`api_product_details.php?id=${id}`);
                    const text = await response.text(); // Récupérer le texte brut de la réponse
                    console.log('Réponse brute:', text); // Afficher la réponse brute dans la console
                    const productDetails = JSON.parse(text);
                    setSelectedProduct(productDetails);
                    setDrawerVisible(true);
                } catch (error) {
                    console.error('Erreur lors de la récupération des détails du produit:', error);
                    console.error('Message d\'erreur:', error.message);
                    message.error(`Erreur lors de la récupération des détails du produit: ${error.message}`);
                }
            };

            const closeDrawer = () => {
                setDrawerVisible(false);
            };

            return (
                <div>
                    <Row gutter={16} className="mb-4">
                        <Col span={8}>
                            <Card>
                                <Statistic
                                    title={`Total des entrées (${inventoryData.period === 'week' ? 'cette semaine' : 'ce mois'})`}
                                    value={inventoryData.totalEntries}
                                    precision={0}
                                    suffix="unités"
                                />
                            </Card>
                        </Col>
                        <Col span={8}>
                            <Card>
                                <Statistic
                                    title={`Total des sorties (${inventoryData.period === 'week' ? 'cette semaine' : 'ce mois'})`}
                                    value={inventoryData.totalOutputs}
                                    precision={0}
                                    suffix="unités"
                                />
                            </Card>
                        </Col>
                        <Col span={8}>
                            <Card>
                                <Select
                                    defaultValue="month"
                                    style={{ width: 120, marginBottom: 16 }}
                                    onChange={(value) => setInventoryData(prev => ({ ...prev, period: value }))}
                                >
                                    <Select.Option value="week">Semaine</Select.Option>
                                    <Select.Option value="month">Mois</Select.Option>
                                </Select>
                                <p>Période : du {new Date(inventoryData.startDate).toLocaleDateString()} au {new Date(inventoryData.endDate).toLocaleDateString()}</p>
                            </Card>
                        </Col>
                    </Row>
                    <Table
                        dataSource={inventoryData.inventory}
                        columns={columns}
                        rowKey="id"
                        loading={loading}
                        scroll={{ x: true }}
                        summary={() => (
                            <Table.Summary.Row>
                                <Table.Summary.Cell index={0} colSpan={4}>Total</Table.Summary.Cell>
                                <Table.Summary.Cell index={4}>{inventoryData.totalEntries}</Table.Summary.Cell>
                                <Table.Summary.Cell index={5}>{inventoryData.totalOutputs}</Table.Summary.Cell>
                                <Table.Summary.Cell index={6}></Table.Summary.Cell>
                            </Table.Summary.Row>
                        )}
                    />
                    <Drawer
                        title="Détails du produit"
                        placement="right"
                        closable={true}
                        onClose={closeDrawer}
                        visible={drawerVisible}
                        width={600}
                    >
                        {selectedProduct && (
                            <div>
                                <h2 className="text-xl font-bold mb-4">Informations du produit</h2>
                                <table className="w-full mb-6 border-collapse border border-gray-300">
                                    <tbody>
                                        <tr><td className="border border-gray-300 p-2"><strong>Code-barres:</strong></td><td className="border border-gray-300 p-2">{selectedProduct.product.barcode}</td></tr>
                                        <tr><td className="border border-gray-300 p-2"><strong>Nom du produit:</strong></td><td className="border border-gray-300 p-2">{selectedProduct.product.product_name}</td></tr>
                                        <tr><td className="border border-gray-300 p-2"><strong>Catégorie:</strong></td><td className="border border-gray-300 p-2">{selectedProduct.product.category}</td></tr>
                                        <tr><td className="border border-gray-300 p-2"><strong>Quantité actuelle:</strong></td><td className="border border-gray-300 p-2">{selectedProduct.product.quantity}</td></tr>
                                        <tr><td className="border border-gray-300 p-2"><strong>Créé le:</strong></td><td className="border border-gray-300 p-2">{selectedProduct.product.created_at}</td></tr>
                                        <tr><td className="border border-gray-300 p-2"><strong>Mis à jour le:</strong></td><td className="border border-gray-300 p-2">{selectedProduct.product.updated_at}</td></tr>
                                    </tbody>
                                </table>

                                <h3 className="text-lg font-bold mb-2">Entrées</h3>
                                <table className="w-full mb-6 border-collapse border border-gray-300">
                                    <thead>
                                        <tr>
                                            <th className="border border-gray-300 p-2">Date</th>
                                            <th className="border border-gray-300 p-2">Quantité</th>
                                            <th className="border border-gray-300 p-2">Provenance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {selectedProduct.entries.map((entry, index) => (
                                            <tr key={index}>
                                                <td className="border border-gray-300 p-2">{entry.date}</td>
                                                <td className="border border-gray-300 p-2">{entry.quantity}</td>
                                                <td className="border border-gray-300 p-2">{entry.provenance}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                <h3 className="text-lg font-bold mb-2">Sorties</h3>
                                <table className="w-full border-collapse border border-gray-300">
                                    <thead>
                                        <tr>
                                            <th className="border border-gray-300 p-2">Date</th>
                                            <th className="border border-gray-300 p-2">Quantité</th>
                                            <th className="border border-gray-300 p-2">Destination</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {selectedProduct.outputs.map((output, index) => (
                                            <tr key={index}>
                                                <td className="border border-gray-300 p-2">{output.date}</td>
                                                <td className="border border-gray-300 p-2">{output.quantity}</td>
                                                <td className="border border-gray-300 p-2">{output.destination}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Drawer>
                </div>
            );
        };

        ReactDOM.render(
            <React.StrictMode>
                <InventoryApp />
            </React.StrictMode>,
            document.getElementById('inventory-app')
        );
    </script>
</body>

</html>