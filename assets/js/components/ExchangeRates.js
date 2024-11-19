import React, {Component} from "react";
import axios from "axios";

class ExchangeRates extends Component {
    constructor(props) {
        super(props);
        const urlParams = new URLSearchParams(window.location.search);
        const initialDate = urlParams.get("date") || new Date().toISOString().split("T")[0];

        this.state = {
            exchangeRates: [],
            error: null,
            selectedDate: initialDate,
        };
    }

    getBaseUrl() {
        return 'https://recruitment-task-fullstack.ddev.site';
    }

    componentDidMount() {
        this.getExchangeRatesData();
    }

    handleDateChange = (event) => {
        const selectedDate = event.target.value;

        const newUrl = `${window.location.pathname}?date=${selectedDate}`;
        window.history.pushState({ path: newUrl }, "", newUrl);

        this.setState({ selectedDate }, () => {
            this.getExchangeRatesData();
        });
    };

    getExchangeRatesData() {
        const { selectedDate } = this.state;

        axios
            .get(this.getBaseUrl() + '/api/exchange-rates', {
                params: { date: selectedDate },
            })
            .then((response) => {
                this.setState({ exchangeRates: response.data, error: null });
            })
            .catch((error) => {
                console.error(error)
                this.setState({ error: error.response.data.message, exchangeRates: [] });
            })
    }

    render() {
        const { exchangeRates, error, selectedDate } = this.state;

        return (
            <div>
                <section className="row-section">
                    <div className="container-fluid">
                        <div className="row mt-5">
                            <div className="col-md-8 offset-md-2">
                                <div className="form-group mt-4 text-center">
                                    <label htmlFor="datePicker" className="form-label">
                                        Wybierz datę:
                                    </label>
                                    <input
                                        type="date"
                                        id="datePicker"
                                        className="form-control"
                                        value={selectedDate}
                                        onChange={this.handleDateChange}
                                        max={new Date().toISOString().split("T")[0]}
                                        min="2023-01-01"
                                    />
                                </div>

                                {error ? (
                                    <div className="alert alert-danger text-center mt-4" role="alert">
                                        {error}
                                    </div>
                                ) : (
                                    <div className="row">
                                        <div className="col-md-6">
                                            <h5 className="text-center">Kursy wymiany walut dla wybranej daty</h5>
                                            <table className="table table-bordered table-hover mt-4">
                                                <thead className="thead-dark">
                                                <tr>
                                                    <th>Nazwa waluty</th>
                                                    <th>Kod</th>
                                                    <th>Kurs średni</th>
                                                    <th>Kurs kupna</th>
                                                    <th>Kurs sprzedaży</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                {exchangeRates.map((item, index) => (
                                                    <tr key={index}>
                                                        <td>{item.currency}</td>
                                                        <td>{item.code}</td>
                                                        <td>{item.mid}</td>
                                                        <td>
                                                            {item.buyRate ?? "Brak ofert"}
                                                        </td>
                                                        <td>
                                                            {item.sellRate ?? "Brak ofert"}
                                                        </td>
                                                    </tr>
                                                ))}
                                                </tbody>
                                            </table>
                                        </div>

                                        <div className="col-md-6">
                                            <h5 className="text-center">Aktualne kursy wymiany walut</h5>
                                            <table className="table table-bordered table-hover mt-4">
                                                <thead className="thead-light">
                                                <tr>
                                                    <th>Aktualny kurs średni</th>
                                                    <th>Aktualny kurs kupna</th>
                                                    <th>Aktualny kurs sprzedaży</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                {exchangeRates.map((item, index) => (
                                                    <tr key={index}>
                                                        <td>{item.todayMid}</td>
                                                        <td>
                                                            {item.todayBuyRate ?? "Brak ofert"}
                                                        </td>
                                                        <td>
                                                            {item.todaySellRate ?? "Brak ofert"}
                                                        </td>
                                                    </tr>
                                                ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}

                                {!error && exchangeRates.length === 0 && (
                                    <p className="text-center mt-4">Ładowanie danych...</p>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        )
    }
}

export default ExchangeRates;
